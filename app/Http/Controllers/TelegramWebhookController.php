<?php

namespace App\Http\Controllers;

use App\Jobs\ProcessCallbackQuery;
use App\Jobs\ProcessTelegramMessage;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class TelegramWebhookController extends Controller
{
    /**
     * Handle incoming Telegram webhook.
     *
     * Per spec:
     * - DON'T run AI calls synchronously inside the webhook handler
     * - telegram_chat_id is your auth — no separate auth system
     * - Handle both message and callback_query update types
     * - Always return 200 to Telegram
     */
    public function __invoke(Request $request): JsonResponse
    {
        // ── Handle text messages ───────────────────────────────────────
        if ($request->has('message.text')) {
            return $this->handleMessage($request);
        }

        // ── Handle callback queries (inline keyboard presses) ──────────
        if ($request->has('callback_query')) {
            return $this->handleCallbackQuery($request);
        }

        // Ignore everything else (photos, stickers, etc.)
        return response()->json(['ok' => true]);
    }

    private function handleMessage(Request $request): JsonResponse
    {
        $message = $request->input('message.text');
        $chatId = $request->input('message.chat.id');
        $username = $request->input('message.from.username');

        if (!$message || !$chatId) {
            return response()->json(['ok' => true]);
        }

        // Optional: restrict to allowed chat IDs
        if (!$this->isAllowedChat($chatId)) {
            Log::warning('Unauthorized chat ID', ['chat_id' => $chatId]);
            return response()->json(['ok' => true]);
        }

        // Find or create user by telegram_chat_id (auth per spec)
        $user = User::findOrCreateByTelegramId((int) $chatId, $username);
        $user->update(['last_active_at' => now()]);

        // Dispatch to queue (async AI processing per spec)
        ProcessTelegramMessage::dispatch($user->id, (string) $chatId, $message);

        return response()->json(['ok' => true]);
    }

    private function handleCallbackQuery(Request $request): JsonResponse
    {
        $callbackQueryId = $request->input('callback_query.id');
        $chatId = $request->input('callback_query.message.chat.id');
        $messageId = $request->input('callback_query.message.message_id');
        $data = $request->input('callback_query.data');

        if (!$callbackQueryId || !$chatId || !$data) {
            return response()->json(['ok' => true]);
        }

        if (!$this->isAllowedChat($chatId)) {
            return response()->json(['ok' => true]);
        }

        $user = User::where('telegram_chat_id', (int) $chatId)->first();

        if (!$user) {
            return response()->json(['ok' => true]);
        }

        $user->update(['last_active_at' => now()]);

        // Dispatch callback processing to queue
        ProcessCallbackQuery::dispatch(
            $user->id,
            (string) $chatId,
            $callbackQueryId,
            $data,
            (int) $messageId
        );

        return response()->json(['ok' => true]);
    }

    private function isAllowedChat(int|string $chatId): bool
    {
        $allowed = config('butler.allowed_chat_ids');
        if (!$allowed) {
            return true; // No restriction
        }

        $allowedIds = array_map('trim', explode(',', $allowed));
        return in_array((string) $chatId, $allowedIds);
    }
}
