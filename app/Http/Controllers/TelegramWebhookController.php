<?php

namespace App\Http\Controllers;

use App\Jobs\ProcessCallbackQuery;
use App\Jobs\ProcessTelegramMessage;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\URL;

/**
 * Telegram Webhook Gateway — Unified State Machine
 *
 * Implements the 4-gate flow from project-butler-integration-guide.md:
 *   Gate 1: Unknown user → "Ketik /start"
 *   Gate 2: /start command → handleStart() (state-aware)
 *   Gate 3: Onboarding incomplete → block + resend setup link
 *   Gate 4: Fully onboarded → ProcessTelegramMessage job (high queue)
 */
class TelegramWebhookController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $update = $request->all();

        // Handle callback queries (button taps) separately
        if (isset($update['callback_query'])) {
            return $this->handleCallbackQuery($update['callback_query']);
        }

        $message = $update['message'] ?? null;
        if (!$message) {
            return response()->json(['ok' => true]);
        }

        // Route photo messages to receipt scanning flow
        if (isset($message['photo'])) {
            $chatId = $message['chat']['id'];
            if (!$this->isAllowedChat($chatId)) {
                return response()->json(['ok' => true]);
            }
            $user = User::where('telegram_chat_id', (int) $chatId)->first();
            if ($user && $user->onboarding_step === 'complete') {
                // Get the highest-resolution photo
                $photos  = $message['photo'];
                $fileId  = end($photos)['file_id'];
                $caption = $message['caption'] ?? '';
                $user->update(['last_active_at' => now()]);
                ProcessTelegramMessage::dispatch($user->id, (string) $chatId, "__photo:{$fileId}|caption:{$caption}")->onQueue('high');
            }
            return response()->json(['ok' => true]);
        }

        if (!isset($message['text'])) {
            return response()->json(['ok' => true]);
        }

        $chatId = $message['chat']['id'];
        $text   = trim($message['text']);

        \Illuminate\Support\Facades\Log::info('WEBHOOK RECEIVED', ['chat_id' => $chatId, 'text' => $text]);

        // Optional: restrict to allowed chat IDs
        if (!$this->isAllowedChat($chatId)) {
            Log::warning('Unauthorized chat ID', ['chat_id' => $chatId]);
            return response()->json(['ok' => true]);
        }

        $user = User::where('telegram_chat_id', (int) $chatId)->first();

        // ── GATE 1: Commands (always handle regardless of onboarding state) ──
        if (str_starts_with($text, '/start')) {
            if (!$user) {
                $user = User::findOrCreateByTelegramId((int) $chatId, $message['from']['username'] ?? null);
            }
            $this->handleStart($message['from'], $user);
            return response()->json(['ok' => true]);
        }

        // ── GATE 2: Unknown user ──────────────────────────────────────
        if (!$user) {
            $this->sendRaw($chatId, 'Ketik /start untuk mulai.');
            return response()->json(['ok' => true]);
        }

        $user->update(['last_active_at' => now()]);

        // ── GATE 3: Onboarding incomplete ────────────────────────────
        if ($user->isOnboardingGated()) {
            $this->handleIncompleteOnboarding($user);
            return response()->json(['ok' => true]);
        }

        // ── GATE 4: Full pipeline for complete users ──────────────────
        ProcessTelegramMessage::dispatch($user->id, (string) $chatId, $text)->onQueue('high');

        return response()->json(['ok' => true]);
    }

    // ── Start Handler ─────────────────────────────────────────────────────

    private function handleStart(array $from, User $user): void
    {
        $username = $from['username'] ?? null;

        // Update username if it changed
        if ($username && $user->telegram_username !== $username) {
            $user->update(['telegram_username' => $username]);
        }

        match (true) {
            // Fully onboarded
            $user->onboarding_step === 'complete' => $this->sendWelcomeBack($user),

            // Mid-onboarding — resend a fresh link
            in_array($user->onboarding_step, [
                'link_sent', 'webview_opened',
                'profile_done', 'accounts_done',
                'budget_done', 'health_done',
            ]) => $this->resendOnboardingLink($user),

            // New or reset
            default => $this->sendOnboardingLink($user),
        };
    }

    private function sendOnboardingLink(User $user): void
    {
        $url = URL::temporarySignedRoute(
            'onboarding.start',
            now()->addMinutes(10),
            ['telegram_id' => $user->telegram_chat_id]
        );

        $user->update(['onboarding_step' => 'link_sent']);

        $this->sendRaw($user->telegram_chat_id, "Halo! Aku Butler, asisten keuangan harianmu.\nSetup butuh sekitar 3 menit.", [
            'inline_keyboard' => [[
                ['text' => '🚀 Mulai Setup', 'url' => $url],
            ]],
        ]);
    }

    private function resendOnboardingLink(User $user): void
    {
        $url = URL::temporarySignedRoute(
            'onboarding.start',
            now()->addMinutes(10),
            ['telegram_id' => $user->telegram_chat_id]
        );

        $stepLabels = [
            'link_sent'      => 'belum dimulai',
            'webview_opened' => 'baru dibuka',
            'profile_done'   => 'di langkah akun',
            'accounts_done'  => 'di langkah budget',
            'budget_done'    => 'di langkah kesehatan',
            'health_done'    => 'hampir selesai',
        ];

        $progress = $stepLabels[$user->onboarding_step] ?? '';

        $this->sendRaw($user->telegram_chat_id, "Setup kamu {$progress}. Lanjutin dari sini:", [
            'inline_keyboard' => [[
                ['text' => '↩ Lanjut Setup', 'url' => $url],
            ]],
        ]);
    }

    private function sendWelcomeBack(User $user): void
    {
        $account = $user->defaultAccount;

        $this->sendRaw($user->telegram_chat_id, implode("\n", [
            "Halo lagi, {$user->name}!",
            "",
            "Akun aktif: " . ($account?->name ?? 'belum diset'),
            "",
            "Contoh:",
            "• makan ayam geprek 35k",
            "• grab 18rb",
            "• rangkuman hari ini",
        ]));
    }

    private function handleIncompleteOnboarding(User $user): void
    {
        $url = URL::temporarySignedRoute(
            'onboarding.start',
            now()->addMinutes(10),
            ['telegram_id' => $user->telegram_chat_id]
        );

        $this->sendRaw($user->telegram_chat_id, 'Setup dulu ya sebelum mulai. Cuma 3 menit.', [
            'inline_keyboard' => [[
                ['text' => '🚀 Lanjut Setup', 'url' => $url],
            ]],
        ]);
    }

    // ── Callback Query ─────────────────────────────────────────────────────

    private function handleCallbackQuery(array $callbackQuery): JsonResponse
    {
        $callbackQueryId = $callbackQuery['id'];
        $chatId          = $callbackQuery['message']['chat']['id'];
        $messageId       = $callbackQuery['message']['message_id'];
        $data            = $callbackQuery['data'];

        if (!$this->isAllowedChat($chatId)) {
            return response()->json(['ok' => true]);
        }

        $user = User::where('telegram_chat_id', (int) $chatId)->first();
        if (!$user) {
            return response()->json(['ok' => true]);
        }

        $user->update(['last_active_at' => now()]);

        ProcessCallbackQuery::dispatch(
            $user->id,
            (string) $chatId,
            $callbackQueryId,
            $data,
            (int) $messageId
        );

        return response()->json(['ok' => true]);
    }

    // ── Helpers ────────────────────────────────────────────────────────────

    private function sendRaw(int|string $chatId, string $text, ?array $replyMarkup = null): void
    {
        $payload = ['chat_id' => $chatId, 'text' => $text];
        if ($replyMarkup) {
            $payload['reply_markup'] = json_encode($replyMarkup);
        }

        try {
            $response = \Illuminate\Support\Facades\Http::post(
                'https://api.telegram.org/bot' . config('butler.telegram.bot_token') . '/sendMessage',
                $payload
            );
            if (!$response->successful()) {
                Log::error('sendRaw failed API response', ['status' => $response->status(), 'body' => $response->body()]);
            }
        } catch (\Throwable $e) {
            Log::error('sendRaw failed exception', ['message' => $e->getMessage()]);
        }
    }

    private function isAllowedChat(int|string $chatId): bool
    {
        $allowed = config('butler.allowed_chat_ids');
        if (!$allowed) {
            return true;
        }

        $allowedIds = array_map('trim', explode(',', $allowed));
        return in_array((string) $chatId, $allowedIds);
    }
}
