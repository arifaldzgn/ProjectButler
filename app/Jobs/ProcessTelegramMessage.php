<?php

namespace App\Jobs;

use App\Events\MessageReceived;
use App\Models\User;
use App\Services\AnalyticsService;
use App\Services\ConversationService;
use App\Services\MessageRouter;
use App\Services\ShortcutMessageService;
use App\Services\TelegramService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class ProcessTelegramMessage implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Per spec: DON'T run AI calls synchronously inside the webhook handler.
     * This job processes the message asynchronously via Laravel Queue.
     *
     * Tag convention for Shortcut API (additive — Telegram msgs pass through unchanged):
     *   Format A: "__shortcut:{id}|{message}"
     *   Format B: "__shortcut:{id}|intent:{hint}|{message}"
     *
     * Response relay: MessageRouter returns void, so we use TelegramService
     * .getLastSentText() to capture the last response without touching MessageRouter.
     */

    public int $userId;
    public string $chatId;
    public string $message;

    public function __construct(int $userId, string $chatId, string $message)
    {
        $this->userId  = $userId;
        $this->chatId  = $chatId;
        $this->message = $message;
    }

    public function handle(
        MessageRouter          $router,
        TelegramService        $telegram,
        ShortcutMessageService $shortcutService,
        ConversationService    $conversations,
        AnalyticsService       $analytics,
    ): void {
        $user = User::find($this->userId);

        if (!$user) {
            Log::warning('ProcessTelegramMessage: user not found', ['user_id' => $this->userId]);
            return;
        }

        // ── Parse Shortcut tag ────────────────────────────────────────────
        $shortcutMessageId = null;
        $intentHint        = null;
        $rawMessage        = $this->message;

        if (str_starts_with($this->message, '__shortcut:')) {
            $withoutPrefix = substr($this->message, strlen('__shortcut:'));
            $parts         = explode('|', $withoutPrefix, 3);

            $shortcutMessageId = (int) $parts[0];

            if (isset($parts[1]) && str_starts_with($parts[1], 'intent:')) {
                // Format B: id | intent:hint | message
                $intentHint = substr($parts[1], strlen('intent:'));
                $rawMessage = $parts[2] ?? '';
            } elseif (isset($parts[1])) {
                // Format A: id | message
                $rawMessage = $parts[1];
            }
        }

        $channel   = $shortcutMessageId ? 'shortcut' : 'telegram';
        $channelId = $shortcutMessageId ? (string) $shortcutMessageId : $this->chatId;

        // ── Fire MessageReceived event (async listeners: conversation log, device touch)
        MessageReceived::dispatch($user, $rawMessage, $channel, $channelId, $shortcutMessageId);

        // Reset capture before routing so stale text from a previous call isn't used
        $telegram->clearLastSentText();
        $startTime = microtime(true);

        try {
            // ── Route through existing MessageRouter — SOURCE OF TRUTH, UNCHANGED
            $router->handle($user, $this->chatId, $rawMessage);

            $latencyMs = (int) ((microtime(true) - $startTime) * 1000);

            // ── Relay last Telegram response back to the Shortcut caller
            if ($shortcutMessageId) {
                $responseText = $telegram->getLastSentText();
                if ($responseText) {
                    $shortcutService->relayResponse($shortcutMessageId, $responseText);
                    $conversations->recordAssistantTurn(
                        user:      $user,
                        channel:   'shortcut',
                        channelId: $channelId,
                        content:   $responseText,
                        extra:     ['ai_latency_ms' => $latencyMs, 'intent' => $intentHint],
                    );
                }
            }

            // ── Analytics (non-blocking, best-effort, low-priority queue via listener)
            $analytics->record($user, $channel, $intentHint ?? 'unknown', $latencyMs);

        } catch (\Throwable $e) {
            Log::error('ProcessTelegramMessage failed', [
                'user_id'             => $this->userId,
                'shortcut_message_id' => $shortcutMessageId,
                'error'               => $e->getMessage(),
            ]);
            $analytics->record($user, $channel, $intentHint ?? 'unknown', 0, true);
        }
    }

    /** Max 2 attempts per spec DON'Ts. */
    public int $tries = 2;
}
