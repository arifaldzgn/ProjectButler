<?php

namespace App\Services;

use App\Jobs\ProcessTelegramMessage;
use App\Models\ShortcutMessage;
use App\Models\User;
use App\Pipeline\MessageContext;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * ShortcutMessageService
 *
 * Handles the full lifecycle of a Shortcut API message:
 *  1. Persist to shortcut_messages table
 *  2. Build a MessageContext (with optional intent hint)
 *  3. Dispatch to the existing ProcessTelegramMessage job queue
 *  4. Optionally wait for the response (sync mode) or return immediately (async)
 *
 * The Telegram Bot Token is NEVER exposed here or in the calling controller.
 * MessageRouter remains the source of truth for business logic.
 */
class ShortcutMessageService
{
    // How long (seconds) to wait for a sync-mode AI response before giving up
    private const SYNC_TIMEOUT_SECONDS = 12;

    // Cache TTL for shortcut response relay (slightly longer than timeout)
    private const RESPONSE_CACHE_TTL = 60;

    /**
     * Process an incoming Shortcut message for the given user.
     *
     * @param  User        $user        Authenticated user (resolved by Sanctum)
     * @param  string      $message     Raw text from the Shortcut
     * @param  string      $source      Client identifier
     * @param  array       $metadata    Optional client metadata
     * @param  string|null $intentHint  Optional intent from client (skips AI parse)
     * @param  int|null    $deviceId    Resolved Device ID for this request
     */
    public function process(
        User    $user,
        string  $message,
        string  $source     = 'iphone_shortcut',
        array   $metadata   = [],
        ?string $intentHint = null,
        ?int    $deviceId   = null,
    ): ShortcutMessage {
        // 1. Persist the incoming message
        $shortcutMessage = ShortcutMessage::create([
            'user_id'  => $user->id,
            'message'  => $message,
            'source'   => $source,
            'status'   => 'pending',
            'metadata' => array_merge($metadata, [
                'submitted_at' => now()->toIso8601String(),
                'ip'           => request()->ip(),
                'intent_hint'  => $intentHint,
                'device_id'    => $deviceId,
            ]),
        ]);

        $chatId = (string) $user->telegram_chat_id;

        // 2. Build a MessageContext and dispatch — intent hint included in tag if provided
        $context = new MessageContext(
            userId:            $user->id,
            message:           $message,
            channel:           'shortcut',
            channelId:         (string) $shortcutMessage->id,
            intentHint:        $intentHint,
            shortcutMessageId: $shortcutMessage->id,
            deviceId:          $deviceId,
            metadata:          $metadata,
        );

        ProcessTelegramMessage::dispatch($user->id, $chatId, $context->toTaggedMessage())
            ->onQueue('high');

        Log::info('ShortcutMessage dispatched', [
            'shortcut_message_id' => $shortcutMessage->id,
            'user_id'             => $user->id,
            'source'              => $source,
            'mode'                => $user->shortcut_mode,
        ]);

        // 3. Sync mode: poll cache for the response the job writes back
        if ($user->shortcut_mode === 'sync') {
            $response = $this->waitForResponse($shortcutMessage->id);
            if ($response !== null) {
                $shortcutMessage->markProcessed($response);
            }
        }

        return $shortcutMessage->fresh();
    }

    /**
     * Relay a processed response back from a queue job to a waiting sync request.
     * Called by ProcessTelegramMessage after the AI pipeline finishes.
     *
     * @param int    $shortcutMessageId
     * @param string $response
     */
    public function relayResponse(int $shortcutMessageId, string $response): void
    {
        $cacheKey = $this->responseCacheKey($shortcutMessageId);
        Cache::put($cacheKey, $response, self::RESPONSE_CACHE_TTL);

        // Also persist to DB regardless of mode
        $record = ShortcutMessage::find($shortcutMessageId);
        $record?->markProcessed($response);
    }

    /**
     * Poll the cache for a job-written response, blocking up to SYNC_TIMEOUT_SECONDS.
     */
    private function waitForResponse(int $shortcutMessageId): ?string
    {
        $cacheKey  = $this->responseCacheKey($shortcutMessageId);
        $deadline  = microtime(true) + self::SYNC_TIMEOUT_SECONDS;
        $sleepMs   = 300_000; // 300ms polling interval

        while (microtime(true) < $deadline) {
            $response = Cache::get($cacheKey);
            if ($response !== null) {
                Cache::forget($cacheKey);
                return $response;
            }
            usleep($sleepMs);
        }

        return null; // Timed out — caller returns 'pending' status
    }

    private function responseCacheKey(int $id): string
    {
        return "shortcut_response:{$id}";
    }

    /**
     * Retrieve a previously submitted message for status polling.
     */
    public function find(int $shortcutMessageId, User $user): ?ShortcutMessage
    {
        return ShortcutMessage::where('id', $shortcutMessageId)
            ->where('user_id', $user->id)
            ->first();
    }
}
