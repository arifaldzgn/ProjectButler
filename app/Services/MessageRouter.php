<?php

namespace App\Services;

use App\Models\Entry;
use App\Models\User;
use Illuminate\Support\Facades\Log;

class MessageRouter
{
    private AIService $ai;
    private EntryService $entries;
    private StreakService $streaks;
    private AiLogService $aiLog;
    private OnboardingService $onboarding;
    private TelegramService $telegram;

    public function __construct(
        AIService $ai,
        EntryService $entries,
        StreakService $streaks,
        AiLogService $aiLog,
        OnboardingService $onboarding,
        TelegramService $telegram
    ) {
        $this->ai = $ai;
        $this->entries = $entries;
        $this->streaks = $streaks;
        $this->aiLog = $aiLog;
        $this->onboarding = $onboarding;
        $this->telegram = $telegram;
    }

    /**
     * Process an incoming Telegram text message.
     *
     * Flow:
     * 1. Check onboarding gate
     * 2. Handle quick commands (/start, /summary)
     * 3. AI parse with Prompt A
     * 4. Confidence-based routing
     * 5. Create pending entry → send inline keyboard
     */
    public function handle(User $user, string $chatId, string $message): void
    {
        // ── Gate 1: Onboarding ─────────────────────────────────────────
        if (!$user->isOnboardingComplete()) {
            $this->onboarding->handle($user, $chatId, $message);
            return;
        }

        // ── Gate 2: Quick commands (no AI needed) ──────────────────────
        if ($this->handleQuickCommand($user, $chatId, $message)) {
            return;
        }

        // ── Gate 3: AI parse ───────────────────────────────────────────
        try {
            $startTime = microtime(true);
            $parsed = $this->ai->parseMessage($message, $user->name);
            $latencyMs = $parsed['_latency_ms'] ?? (int) ((microtime(true) - $startTime) * 1000);

            if (!$parsed) {
                $this->telegram->sendMessage($chatId, $this->telegram->getParseErrorResponse());
                return;
            }

            // Log the AI call
            $this->aiLog->logParseCall($user, $message, $parsed, $latencyMs);

        } catch (\Exception $e) {
            $latencyMs = (int) ((microtime(true) - ($startTime ?? microtime(true))) * 1000);
            $this->aiLog->logParseCall($user, $message, null, $latencyMs, false, $e->getMessage());

            $errorMsg = config('app.debug')
                ? $this->telegram->getParseErrorResponse() . "\n\n[Debug] " . substr($e->getMessage(), 0, 500)
                : $this->telegram->getParseErrorResponse();

            $this->telegram->sendMessage($chatId, $errorMsg);
            return;
        }

        // ── Gate 4: Route by intent ────────────────────────────────────
        $intent = $parsed['intent'] ?? 'general';
        $confidence = (float) ($parsed['confidence'] ?? 0.5);

        match ($intent) {
            'expense', 'meal', 'saving' => $this->handleEntry($user, $chatId, $message, $parsed, $confidence),
            'query' => $this->handleQuery($user, $chatId, $parsed),
            'general' => $this->handleGeneral($user, $chatId, $message, $parsed),
            default => $this->telegram->sendMessage($chatId, $parsed['message'] ?? '🤔 Butler belum ngerti nih...'),
        };
    }

    /**
     * Handle a Telegram callback query (inline keyboard button press).
     *
     * Callback data format: "action:entryId"
     * Actions: confirm, edit, cancel
     */
    public function handleCallbackQuery(User $user, string $chatId, string $callbackQueryId, string $data, int $messageId): void
    {
        $parts = explode(':', $data, 2);
        $action = $parts[0] ?? '';
        $entryId = (int) ($parts[1] ?? 0);

        if (!$entryId) {
            $this->telegram->answerCallbackQuery($callbackQueryId, 'Data tidak valid.');
            return;
        }

        // Find the entry — must belong to this user and be pending
        $entry = Entry::forUser($user->id)->pending()->find($entryId);

        if (!$entry) {
            $this->telegram->answerCallbackQuery($callbackQueryId, 'Entry tidak ditemukan atau sudah diproses.');
            $this->telegram->editMessage($chatId, $messageId, '❌ Entry sudah tidak tersedia.');
            return;
        }

        match ($action) {
            'confirm' => $this->confirmEntry($user, $chatId, $callbackQueryId, $entry, $messageId),
            'edit' => $this->editEntry($user, $chatId, $callbackQueryId, $entry, $messageId),
            'cancel' => $this->cancelEntry($user, $chatId, $callbackQueryId, $entry, $messageId),
            default => $this->telegram->answerCallbackQuery($callbackQueryId, 'Aksi tidak dikenal.'),
        };
    }

    // ════════════════════════════════════════════════════════════════════
    // QUICK COMMANDS
    // ════════════════════════════════════════════════════════════════════

    private function handleQuickCommand(User $user, string $chatId, string $message): bool
    {
        $msg = strtolower(trim($message));

        // /start for returning users — just show help
        if ($msg === '/start') {
            $this->sendHelp($user, $chatId);
            return true;
        }

        if (in_array($msg, ['/summary', 'summary', 'ringkasan'])) {
            $this->sendQuickSummary($user, $chatId);
            return true;
        }

        if (in_array($msg, ['/help', 'help', 'bantuan'])) {
            $this->sendHelp($user, $chatId);
            return true;
        }

        return false;
    }

    // ════════════════════════════════════════════════════════════════════
    // ENTRY HANDLING (with confidence-based routing)
    // ════════════════════════════════════════════════════════════════════

    /**
     * Handle expense/meal/saving entry creation.
     *
     * Per spec confidence behavior:
     * ≥ 0.90 → Auto-confirm with inline keyboard (default tap = save)
     * 0.75–0.89 → Show confirmation with parsed data, require explicit confirm
     * 0.50–0.74 → Show parsed data, highlight uncertain fields, ask to verify
     * < 0.50 → Don't guess — ask clarifying question
     */
    private function handleEntry(User $user, string $chatId, string $rawMessage, array $parsed, float $confidence): void
    {
        $intent = $parsed['intent'];

        // < 0.50: Don't guess — ask clarifying question
        if ($confidence < 0.50) {
            $this->telegram->sendMessage($chatId, $this->telegram->getLowConfidenceResponse());
            return;
        }

        // Create pending entry
        $entry = $this->entries->createPendingEntry($user, $parsed, $rawMessage);

        // Format the confirmation message
        $confirmationText = match ($intent) {
            'expense' => $this->telegram->formatExpenseConfirmation($parsed, $confidence),
            'meal' => $this->telegram->formatMealConfirmation($parsed, $confidence),
            'saving' => $this->telegram->formatSavingConfirmation($parsed),
            default => '📝 Data terdeteksi.',
        };

        // Build inline keyboard buttons
        $buttons = $this->telegram->buildConfirmationButtons($entry->id);

        // Send with inline keyboard
        $this->telegram->sendMessageWithInlineKeyboard($chatId, $confirmationText, $buttons);
    }

    // ════════════════════════════════════════════════════════════════════
    // CALLBACK QUERY HANDLERS
    // ════════════════════════════════════════════════════════════════════

    private function confirmEntry(User $user, string $chatId, string $callbackQueryId, Entry $entry, int $messageId): void
    {
        // Confirm the entry
        $this->entries->confirmEntry($entry);

        // Update streaks
        $this->streaks->updateAfterConfirmation($user, $entry->type);

        // Build confirmed message with totals
        $todayTotal = match ($entry->type) {
            'expense' => $this->entries->getTodaySpending($user),
            'meal' => $this->entries->getTodayCalories($user),
            'saving' => $this->entries->getTotalSavings($user),
            default => 0,
        };

        $remaining = match ($entry->type) {
            'expense' => $this->entries->getBudgetRemaining($user),
            'meal' => $user->daily_calorie_goal,
            default => null,
        };

        $parsed = $this->entryToParsedArray($entry);
        $confirmedMsg = $this->telegram->formatConfirmedMessage($entry->type, $parsed, $todayTotal, $remaining);

        // Update the message (remove keyboard, show confirmed)
        $this->telegram->editMessage($chatId, $messageId, $confirmedMsg);
        $this->telegram->answerCallbackQuery($callbackQueryId, '✅ Disimpan!');
    }

    private function editEntry(User $user, string $chatId, string $callbackQueryId, Entry $entry, int $messageId): void
    {
        // Per spec: "User taps Edit → prompt them to retype (keep it simple)"
        $this->entries->cancelEntry($entry);

        $this->telegram->editMessage($chatId, $messageId,
            "✏️ Oke, coba ketik ulang dengan format yang benar ya!\n"
            . "Contoh: `50k makan siang` atau `grab 23rb`"
        );
        $this->telegram->answerCallbackQuery($callbackQueryId, '✏️ Silakan ketik ulang.');
    }

    private function cancelEntry(User $user, string $chatId, string $callbackQueryId, Entry $entry, int $messageId): void
    {
        $this->entries->cancelEntry($entry);

        $this->telegram->editMessage($chatId, $messageId, '❌ Dibatalkan. Tidak ada yang disimpan.');
        $this->telegram->answerCallbackQuery($callbackQueryId, '❌ Dibatalkan.');
    }

    // ════════════════════════════════════════════════════════════════════
    // QUERY HANDLING
    // ════════════════════════════════════════════════════════════════════

    private function handleQuery(User $user, string $chatId, array $parsed): void
    {
        $queryType = $parsed['query_type'] ?? 'summary';

        $response = match ($queryType) {
            'spending_today' => $this->telegram->formatSpendingTodayResponse(
                $this->entries->getTodaySpending($user),
                $this->entries->getBudgetRemaining($user)
            ),
            'spending_month' => $this->buildSpendingMonthResponse($user),
            'calories_today' => $this->telegram->formatCaloriesTodayResponse(
                $this->entries->getTodayCalories($user),
                $user->daily_calorie_goal
            ),
            'summary', 'balance' => $this->buildQuickSummaryText($user),
            default => '🤔 Data tidak ditemukan.',
        };

        $this->telegram->sendMessage($chatId, $response);
    }

    private function handleGeneral(User $user, string $chatId, string $message, array $parsed): void
    {
        $response = $parsed['message'] ?? $this->ai->chat($message);
        $this->telegram->sendMessage($chatId, $response);
    }

    // ════════════════════════════════════════════════════════════════════
    // RESPONSE BUILDERS
    // ════════════════════════════════════════════════════════════════════

    private function sendHelp(User $user, string $chatId): void
    {
        $help = "🤖 *Butler - Asisten Harianmu*\n\n"
            . "Kirim pesan biasa, Butler otomatis ngerti!\n\n"
            . "💸 *Pengeluaran:*\n"
            . "• `makan nasi goreng 35k`\n"
            . "• `grab 23rb`\n\n"
            . "🍽️ *Makanan:*\n"
            . "• `makan nasi goreng`\n"
            . "• `lunch mie ayam + es teh`\n\n"
            . "💎 *Tabungan:*\n"
            . "• `nabung 500rb`\n"
            . "• `save 1jt buat emergency`\n\n"
            . "📊 *Cek Data:*\n"
            . "• `summary` / `ringkasan`\n"
            . "• `berapa pengeluaran hari ini?`";

        $this->telegram->sendMessage($chatId, $help);
    }

    private function sendQuickSummary(User $user, string $chatId): void
    {
        $response = $this->buildQuickSummaryText($user);
        $this->telegram->sendMessage($chatId, $response);
    }

    private function buildQuickSummaryText(User $user): string
    {
        $spending = $this->entries->getTodaySpending($user);
        $calories = $this->entries->getTodayCalories($user);
        $savings = $this->entries->getTotalSavings($user);
        $remaining = $this->entries->getBudgetRemaining($user);
        $streak = $user->streak;

        $spendF = number_format($spending, 0, ',', '.');
        $savingsF = number_format($savings, 0, ',', '.');

        $msg = "📊 *Ringkasan Hari Ini*\n\n"
            . "💸 Pengeluaran: Rp {$spendF}\n";

        if ($remaining !== null) {
            $remainF = number_format(abs($remaining), 0, ',', '.');
            $msg .= $remaining >= 0
                ? "💰 Sisa budget: Rp {$remainF}\n"
                : "⚠️ Melebihi budget: Rp {$remainF}\n";
        }

        if ($calories > 0) {
            $msg .= "🔥 Kalori: {$calories}";
            if ($user->daily_calorie_goal) {
                $msg .= "/{$user->daily_calorie_goal}";
            }
            $msg .= " kcal\n";
        }

        $msg .= "💎 Total Tabungan: Rp {$savingsF}\n";

        if ($streak && $streak->log_current > 0) {
            $msg .= "\n🔥 Streak: {$streak->log_current} hari berturut-turut!";
        }

        return $msg;
    }

    private function buildSpendingMonthResponse(User $user): string
    {
        $total = $this->entries->getMonthSpending($user);
        $formatted = number_format($total, 0, ',', '.');

        $msg = "💸 Total pengeluaran bulan ini: *Rp {$formatted}*";

        if ($user->daily_budget_idr) {
            $now = \Carbon\Carbon::now($user->timezone);
            $daysInMonth = $now->daysInMonth;
            $monthlyBudget = $user->daily_budget_idr * $daysInMonth;
            $budgetF = number_format($monthlyBudget, 0, ',', '.');
            $pct = $monthlyBudget > 0 ? round(($total / $monthlyBudget) * 100) : 0;
            $msg .= "\n🎯 Budget: Rp {$budgetF} ({$pct}%)";
        }

        return $msg;
    }

    /**
     * Convert an Entry model back to the parsed array format for message formatting.
     */
    private function entryToParsedArray(Entry $entry): array
    {
        return match ($entry->type) {
            'expense' => [
                'amount' => $entry->amount,
                'category' => $entry->category,
                'merchant' => $entry->merchant,
                'note' => $entry->note,
            ],
            'meal' => [
                'food_item' => $entry->food_item,
                'calories' => $entry->calories,
                'is_calorie_estimated' => $entry->is_calorie_estimated,
            ],
            'saving' => [
                'amount' => $entry->amount,
                'note' => $entry->note,
            ],
            default => [],
        };
    }
}
