<?php

namespace App\Services;

use App\Jobs\ProcessBehavioralCorrection;
use App\Jobs\UpdateBehavioralMemory;
use App\Models\Category;
use App\Models\Entry;
use App\Models\Fund;
use App\Models\MoodLog;
use App\Models\User;
use App\Services\BehavioralMemoryService;
use App\Services\DevicePairingService;
use App\Services\PolicyEngine;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;

class MessageRouter
{
    private AIService $ai;
    private EntryService $entries;
    private StreakService $streaks;
    private AiLogService $aiLog;
    private OnboardingService $onboarding;
    private TelegramService $telegram;
    private FundService $funds;
    private BillService $bills;
    private DebtService $debts;
    private PolicyEngine $policy;
    private BehavioralMemoryService $memory;
    private CommandSuggestionService $suggester;
    private DevicePairingService $pairing;

    public function __construct(
        AIService $ai,
        EntryService $entries,
        StreakService $streaks,
        AiLogService $aiLog,
        OnboardingService $onboarding,
        TelegramService $telegram,
        FundService $funds,
        BillService $bills,
        DebtService $debts,
        PolicyEngine $policy,
        BehavioralMemoryService $memory,
        CommandSuggestionService $suggester,
        DevicePairingService $pairing
    ) {
        $this->ai         = $ai;
        $this->entries    = $entries;
        $this->streaks    = $streaks;
        $this->aiLog      = $aiLog;
        $this->onboarding = $onboarding;
        $this->telegram   = $telegram;
        $this->funds      = $funds;
        $this->bills      = $bills;
        $this->debts      = $debts;
        $this->policy     = $policy;
        $this->memory     = $memory;
        $this->suggester  = $suggester;
        $this->pairing    = $pairing;
    }

    /**
     * Try the suggestion engine, send the suggestion if found, log the event,
     * and return true if a suggestion reply was sent (caller should stop).
     * Returns false if nothing matched — caller should send the canned fallback.
     */
    private function trySendSuggestion(
        ?User $user,
        string $chatId,
        string $message,
        ?float $confidence,
        string $reason
    ): bool {
        $suggestion = $this->suggester->suggest($message);
        $this->aiLog->logUnrecognized($user, $message, $confidence, $suggestion, $reason);

        if ($suggestion && !empty($suggestion['reply'])) {
            $this->telegram->sendMessage($chatId, $suggestion['reply']);
            return true;
        }
        return false;
    }

    /**
     * Process an incoming Telegram text message.
     */
    public function handle(User $user, string $chatId, string $message): void
    {
        // ── Gate 1: Onboarding ─────────────────────────────────────────
        if (!$user->isOnboardingComplete()) {
            $this->onboarding->handle($user, $chatId, $message);
            return;
        }

        // ── Gate 0: Photo / Receipt scan ───────────────────────────────
        if (str_starts_with($message, '__photo:')) {
            $this->handleReceiptPhoto($user, $chatId, $message);
            return;
        }

        // ── Gate 2a: Pending calorie correction ────────────────────────
        if ($this->handleCalorieCorrection($user, $chatId, $message)) {
            return;
        }

        // ── Gate 2: Quick commands ──────────────────────────────────────
        if ($this->handleQuickCommand($user, $chatId, $message)) {
            return;
        }

        // ── Gate 3: AI parse ────────────────────────────────────────────
        // Pass user's custom category names so the AI can use them
        $userCategoryNames = Category::forUser($user->id)
            ->ordered()
            ->pluck('name')
            ->toArray();

        // Ground the parser in this user's real data: actual fund names,
        // previously-corrected food calories, and active tracking mode.
        $userContext = $this->buildParserContext($user);

        $startTime = microtime(true);
        try {
            $parsed = $this->ai->parseMessage($message, $user->name, $userCategoryNames, $userContext);
            $latencyMs = $parsed['_latency_ms'] ?? (int) ((microtime(true) - $startTime) * 1000);

            if (!$parsed) {
                $this->telegram->sendMessage($chatId, $this->telegram->getParseErrorResponse());
                return;
            }

            $tokenUsage = $this->ai->getLastTokenUsage();
            $this->aiLog->logParseCall($user, $message, $parsed, $latencyMs, true, null, $tokenUsage);

        } catch (\Exception $e) {
            $latencyMs = (int) ((microtime(true) - $startTime) * 1000);
            $this->aiLog->logParseCall($user, $message, null, $latencyMs, false, $e->getMessage());

            // Smart fallback: try keyword/AI suggestion before the canned reply.
            if ($this->trySendSuggestion($user, $chatId, $message, null, 'parse_exception')) {
                return;
            }

            $errMsg = config('app.debug')
                ? $this->telegram->getParseErrorResponse() . "\n\n[Debug] " . substr($e->getMessage(), 0, 300)
                : $this->telegram->getParseErrorResponse();

            $this->telegram->sendMessage($chatId, $errMsg);
            return;
        }

        // ── Gate 4: Route by intent ─────────────────────────────────────
        $intent = $parsed['intent'] ?? 'unknown';
        $confidence = (float) ($parsed['confidence'] ?? 0.5);

        $confStr = number_format($confidence, 2);
        $latency = $parsed['_latency_ms'] ?? $latencyMs;
        $modelUsed = $parsed['_model_used'] ?? 'unknown';
        $debugMsg = "🤖 *AI Debug*\nInput: `{$message}`\nModel: `{$modelUsed}`\nIntent: `{$intent}`\nConf: `{$confStr}`\nLatency: `{$latency}ms`";
        $this->telegram->setDebugContext($debugMsg);

        // Loggable entry intents (including dual log)
        $logIntents = ['log_expense', 'log_meal', 'log_saving', 'log_income',
                       'log_bill_payment', 'log_debt_payment', 'log_sinking_deposit',
                       'log_meal_and_expense'];

        if (in_array($intent, $logIntents)) {
            $this->handleEntry($user, $chatId, $message, $parsed, $confidence);
            return;
        }

        match ($intent) {
            'add_bill' => $this->handleAddBill($user, $chatId, $parsed),
            'add_sinking_fund' => $this->handleAddSinkingFund($user, $chatId, $parsed),
            'query_balance' => $this->handleQueryBalance($user, $chatId, $parsed),
            'query_summary' => $this->sendQuickSummary($user, $chatId),
            'query_spending' => $this->handleQuerySpending($user, $chatId, $parsed),
            'set_reminder' => $this->handleSetReminder($user, $chatId, $parsed),
            'transfer_fund' => $this->handleTransfer($user, $chatId, $message, $parsed, $confidence),
            'unknown' => $this->handleUnknownIntent($user, $chatId, $message, $parsed, $confidence),
            default   => $this->telegram->sendMessage($chatId, $this->ai->chat($message)),
        };
    }


    /**
     * Handle callback query (inline keyboard button press).
     */
    public function handleCallbackQuery(User $user, string $chatId, string $callbackQueryId, string $data, int $messageId): void
    {
        // ── Onboarding callbacks ────────────────────────────────────────
        if (str_starts_with($data, 'onboard:')) {
            $this->handleOnboardingCallback($user, $chatId, $callbackQueryId, $data, $messageId);
            return;
        }

        // ── Fund source selection callbacks ─────────────────────────────
        if (str_starts_with($data, 'fund_src:')) {
            $this->handleFundSourceCallback($user, $chatId, $callbackQueryId, $data);
            return;
        }

        // ── v2.1: Account selection (needs_clarification mode) ──────────
        if (str_starts_with($data, 'acct_sel:')) {
            $this->handleAccountSelectionCallback($user, $chatId, $callbackQueryId, $data, $messageId);
            return;
        }

        // ── v2.6.1: Transfer account pick (source/target clarification) ──
        if (str_starts_with($data, 'xfer_pick:')) {
            $this->handleTransferPickCallback($user, $chatId, $callbackQueryId, $data, $messageId);
            return;
        }

        // ── v2.5: Calorie edit callback ─────────────────────────────────
        if (str_starts_with($data, 'cal_edit:')) {
            $this->handleCalorieEditCallback($user, $chatId, $callbackQueryId, $data);
            return;
        }

        // ── v2.1: Undo callback ──────────────────────────────────────────
        if (str_starts_with($data, 'undo:')) {
            $this->handleUndoCallback($user, $chatId, $callbackQueryId, $data, $messageId);
            return;
        }

        // ── v2.1: Behavioral memory consent ─────────────────────────────
        if (str_starts_with($data, 'consent_yes:') || str_starts_with($data, 'consent_no:')) {
            $this->handleConsentCallback($user, $chatId, $callbackQueryId, $data, $messageId);
            return;
        }

        // ── Device pairing callbacks ──────────────────────────────────
        if (str_starts_with($data, 'pair_device:')) {
            $this->handlePairDeviceCallback($user, $chatId, $callbackQueryId, $data, $messageId);
            return;
        }

        // ── Entry confirmation callbacks ────────────────────────────────
        $parts  = explode(':', $data, 2);
        $action = $parts[0] ?? '';
        $entryId = (int) ($parts[1] ?? 0);

        if (!$entryId) {
            $this->telegram->answerCallbackQuery($callbackQueryId, 'Data tidak valid.');
            return;
        }

        $entry = Entry::forUser($user->id)->pending()->find($entryId);

        if (!$entry) {
            $this->telegram->answerCallbackQuery($callbackQueryId, 'Entry tidak ditemukan atau sudah diproses.');
            $this->telegram->editMessage($chatId, $messageId, '❌ Entry sudah tidak tersedia.');
            return;
        }

        match ($action) {
            'confirm' => $this->confirmEntry($user, $chatId, $callbackQueryId, $entry, $messageId),
            'edit'    => $this->editEntry($chatId, $callbackQueryId, $entry, $messageId),
            'cancel'  => $this->cancelEntry($chatId, $callbackQueryId, $entry, $messageId),
            default   => $this->telegram->answerCallbackQuery($callbackQueryId, 'Aksi tidak dikenal.'),
        };
    }

    /**
     * Handle "from which fund?" selection callback.
     */
    private function handleFundSourceCallback(User $user, string $chatId, string $callbackQueryId, string $data): void
    {
        // fund_src:{entryId}:{fundId|daily|skip}
        $parts = explode(':', $data);
        $entryId = (int) ($parts[1] ?? 0);
        $fundTarget = $parts[2] ?? 'skip';

        $this->telegram->answerCallbackQuery($callbackQueryId);

        if ($fundTarget === 'skip' || $fundTarget === 'daily') {
            // No fund assignment — just confirm the entry normally
            $entry = Entry::forUser($user->id)->pending()->find($entryId);
            if ($entry) {
                $this->entries->confirmEntry($entry);
                $this->streaks->updateAfterConfirmation($user, $entry->type);
                $this->applyBillDebtEffect($user, $entry);
                $spent = $this->entries->getTodaySpending($user);
                $remaining = $this->entries->getBudgetRemaining($user);
                $parsed = $this->entryToParsedArray($entry);
                $msg = $this->telegram->formatConfirmedMessage($entry->type, $parsed, $spent, $remaining);
                $this->telegram->sendMessage($chatId, $msg);
            }
            return;
        }

        // Assign to a specific fund
        $fundId = (int) $fundTarget;
        $fund = \App\Models\Fund::forUser($user->id)->find($fundId);
        $entry = Entry::forUser($user->id)->pending()->find($entryId);

        if (!$entry || !$fund) {
            $this->telegram->sendMessage($chatId, 'Entry atau dana tidak ditemukan.');
            return;
        }

        $entry->update(['source_fund_id' => $fund->id, 'source_fund_confirmed' => true]);
        $this->entries->confirmEntry($entry);
        $this->streaks->updateAfterConfirmation($user, $entry->type);
        $this->funds->debitFund($fund, $entry->amount, $entry->id, $entry->note);

        $balF = 'Rp ' . number_format($fund->current_balance, 0, ',', '.');
        $this->telegram->sendMessage($chatId,
            "✅ Dicatat dari *{$fund->name}*!\nSaldo sekarang: {$balF}"
        );
    }

    // ════════════════════════════════════════════════════════════════════
    // QUICK COMMANDS
    // ════════════════════════════════════════════════════════════════════

    private function handleQuickCommand(User $user, string $chatId, string $message): bool
    {
        $msg = strtolower(trim($message));

        if ($msg === 'buka dashboard' || $msg === '/dashboard') {
            $url = \Illuminate\Support\Facades\URL::temporarySignedRoute(
                'dashboard.auth',
                now()->addMinutes(30),
                ['telegram_id' => $user->telegram_chat_id]
            );
            
            $this->telegram->sendMessageWithInlineKeyboard(
                $chatId,
                "Ini link dashboard kamu (berlaku 30 menit):",
                [['text' => '📊 Buka Dashboard', 'url' => $url]]
            );
            return true;
        }

        if (in_array($msg, ['halo', 'hai', 'hello', 'hi'])) {
            $this->telegram->sendMessage($chatId, "Halo, {$user->name}! Ada yang bisa Butler bantu catat hari ini?");
            return true;
        }

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
        if (in_array($msg, ['/tagihan', 'tagihan', 'list tagihan', 'daftar tagihan'])) {
            $this->sendBillList($user, $chatId);
            return true;
        }
        if (in_array($msg, ['/settings', 'settings', 'pengaturan', 'ubah profil', 'ganti pengaturan'])) {
            $this->sendSettingsLink($user, $chatId);
            return true;
        }

        if (in_array($msg, ['/pair_iphone', '/pair', 'pair iphone', 'hubungkan iphone', 'pasang shortcut'])) {
            $this->sendPairIphoneInstructions($user, $chatId);
            return true;
        }

        if (in_array($msg, ['/my_devices', '/devices', 'perangkat saya', 'daftar perangkat'])) {
            $this->sendDevicesList($user, $chatId);
            return true;
        }
        // Mood logging: "mood: good" / "mood: bagus, energi 4" / "mood good"
        if (str_starts_with($msg, 'mood') && (str_contains($msg, ':') || strlen($msg) > 4)) {
            if ($this->handleMoodLog($user, $chatId, $message)) {
                return true;
            }
        }

        // Direct balance shortcut — bypass AI entirely
        if (in_array($msg, ['saldo', 'balance', '/saldo', '/balance', 'cek saldo', 'lihat saldo'])) {
            $this->handleQueryBalance($user, $chatId, []);
            return true;
        }

        // Budget suggestion quick command
        if (in_array($msg, ['saran budget', 'budget suggestion', '/saran budget', 'saran keuangan', '/saranbudget'])) {
            app(\App\Services\BudgetSuggestionService::class)->sendSuggestion($user);
            return true;
        }

        // Natural language balance queries → skip AI, go direct
        $balanceKeywords = [
            'tampilkan semua tabungan', 'tampilkan semua uang', 'tampilkan semua dana',
            'lihat tabungan', 'lihat semua uang', 'lihat dana', 'cek tabungan',
            'saldo saya', 'semua dana saya', 'semua saldo',
        ];
        foreach ($balanceKeywords as $kw) {
            if (str_contains($msg, $kw)) {
                $this->handleQueryBalance($user, $chatId, ['query_target' => 'all_funds']);
                return true;
            }
        }

        return false;
    }

    /**
     * Handler for AI-returned intent='unknown' — third fallback site.
     */
    private function handleUnknownIntent(User $user, string $chatId, string $message, array $parsed, float $confidence): void
    {
        if ($this->trySendSuggestion($user, $chatId, $message, $confidence, 'unknown_intent')) {
            return;
        }
        // Suggestion engine had no match → fall back to the AI's clarification
        // message if present, otherwise the generic canned reply.
        $this->telegram->sendMessage(
            $chatId,
            $parsed['message'] ?? $this->telegram->getLowConfidenceResponse()
        );
    }

    // ════════════════════════════════════════════════════════════════════
    // ENTRY HANDLING
    // ════════════════════════════════════════════════════════════════════

    private function handleEntry(User $user, string $chatId, string $rawMessage, array $parsed, float $confidence): void
    {
        if ($confidence < 0.50) {
            // Smart fallback: try suggestion before the canned reply.
            if ($this->trySendSuggestion($user, $chatId, $rawMessage, $confidence, 'low_confidence')) {
                return;
            }
            $this->telegram->sendMessage($chatId, $this->telegram->getLowConfidenceResponse());
            return;
        }

        $intent = $parsed['intent'];

        // ── Dual log: food message that also has an amount ────────────
        if ($intent === 'log_meal_and_expense') {
            $this->handleDualLog($user, $chatId, $rawMessage, $parsed, $confidence);
            return;
        }

        if ($intent === 'log_expense' && $user->isCalorieMode() && !empty($parsed['food_item'])) {
            $this->handleDualLog($user, $chatId, $rawMessage, $parsed, $confidence);
            return;
        }

        $entry = $this->entries->createPendingEntry($user, $parsed, $rawMessage);

        // ── v2.1: Resolve → Policy pipeline for expense entries ───────
        if (in_array($intent, ['log_expense', 'log_bill_payment', 'log_meal_and_expense', 'log_sinking_deposit'])) {
            [$accountSource, $accountConfidence, $autoApply, $resolvedFund] =
                $this->resolveAccountForEntry($user, $parsed, $entry);

            $interactionMode = $this->policy->resolveInteractionMode(
                $accountSource, $accountConfidence, $autoApply
            );

            if ($interactionMode === PolicyEngine::MODE_NEEDS_CLARIFICATION) {
                // High-confidence messages skip clarification and fall through to default fund
                if ($confidence < 0.90) {
                    $this->askAccountSelection($chatId, $entry->id, $user);
                    return;
                }
                $parsed['deducted_from'] = $user->getDefaultSpendingFund()?->name ?? 'Akun Utama';
            }

            if ($interactionMode === PolicyEngine::MODE_SOFT_CONFIRMATION) {
                // Show suggestion as pre-selected in confirmation text
                $confirmationText = $this->buildSoftConfirmationText($parsed, $confidence, $resolvedFund);
                $buttons = $this->telegram->buildConfirmationButtons($entry->id);
                $this->telegram->sendMessageWithInlineKeyboard($chatId, $confirmationText, $buttons);
                return;
            }

            // explicit_input or auto_apply: assign fund and confirm directly
            if ($resolvedFund) {
                if (!$entry->source_fund_id) {
                    $entry->update(['source_fund_id' => $resolvedFund->id, 'source_fund_confirmed' => true]);
                }
                $parsed['deducted_from'] = $resolvedFund->name;
            } else {
                $parsed['deducted_from'] = $user->getDefaultSpendingFund()?->name ?? 'Akun Utama';
            }
        }

        // ── High confidence (≥0.90) or auto_apply → confirm immediately ─
        if ($confidence >= 0.90) {
            $this->confirmAndSendWithUndo($user, $chatId, $entry);
            return;
        }

        // ── Medium confidence → show confirmation keyboard ────────────
        $confirmationText = match ($intent) {
            'log_expense'         => $this->telegram->formatExpenseConfirmation($parsed, $confidence),
            'log_meal'            => $this->telegram->formatMealConfirmation($parsed, $confidence),
            'log_saving'          => $this->telegram->formatSavingConfirmation($parsed),
            'log_income'          => $this->telegram->formatIncomeConfirmation($parsed, $confidence),
            'log_bill_payment'    => $this->telegram->formatBillPaymentConfirmation($parsed, $confidence),
            'log_debt_payment'    => $this->telegram->formatDebtPaymentConfirmation($parsed, $confidence),
            'log_sinking_deposit' => $this->telegram->formatSinkingDepositConfirmation($parsed, $confidence),
            'transfer_fund'       => $this->telegram->formatTransferConfirmation($parsed, $confidence),
            default               => '📝 Data terdeteksi.',
        };

        $buttons = $this->telegram->buildConfirmationButtons($entry->id);
        $this->telegram->sendMessageWithInlineKeyboard($chatId, $confirmationText, $buttons);
    }

    /**
     * Dual log: message has both food + money → log as meal AND expense.
     * e.g. "makan nasi goreng pakai telur 25rb"
     */
    private function handleDualLog(User $user, string $chatId, string $rawMessage, array $parsed, float $confidence): void
    {
        $responses = [];

        // Log meal side (if calorie mode)
        if ($user->isCalorieMode() && !empty($parsed['food_item'])) {
            $mealParsed = [
                'intent'               => 'log_meal',
                'confidence'           => $parsed['confidence'] ?? $confidence,
                'food_item'            => $parsed['food_item'],
                'calories'             => $parsed['calories'] ?? null,
                'is_calorie_estimated' => $parsed['is_calorie_estimated'] ?? true,
                'note'                 => $parsed['note'] ?? null,
            ];
            $mealEntry = $this->entries->createPendingEntry($user, $mealParsed, $rawMessage);
            $this->entries->confirmEntry($mealEntry);
            $totalCal = $this->entries->getTodayCalories($user);
            $calGoal  = $user->daily_calorie_goal;
            $calStr   = $calGoal ? "{$totalCal}/{$calGoal} kcal" : "{$totalCal} kcal";
            $responses[] = "🍽️ *{$mealParsed['food_item']}* — " . ($mealParsed['calories'] ?? '?') . " kcal dicatat.\n🔥 Total hari ini: {$calStr}";
        }

        // Log expense side (if finance mode and amount present)
        if ($user->isFinanceMode() && !empty($parsed['amount'])) {
            $expenseParsed = [
                'intent'     => 'log_expense',
                'confidence' => $parsed['confidence'] ?? $confidence,
                'amount'     => $parsed['amount'],
                'category'   => $parsed['category'] ?? 'food_drink',
                'merchant'   => $parsed['merchant'] ?? null,
                'note'       => $parsed['food_item'] ?? $parsed['note'] ?? null,
                'fund_name'  => $parsed['account_name'] ?? $parsed['fund_name'] ?? null,
            ];
            $expenseEntry = $this->entries->createPendingEntry($user, $expenseParsed, $rawMessage);
            $this->entries->confirmEntry($expenseEntry);
            $this->streaks->updateAfterConfirmation($user, 'expense');
            $this->applyFundEffect($user, $expenseEntry);
            $this->applyBillDebtEffect($user, $expenseEntry);
            
            $expenseEntry->load('sourceFund');
            $fundName   = $expenseEntry->sourceFund ? $expenseEntry->sourceFund->name : 'Akun Utama';
            
            $totalSpent = $this->entries->getTodaySpending($user);
            $remaining  = $this->entries->getBudgetRemaining($user);
            $spentF     = number_format($totalSpent, 0, ',', '.');
            $line       = "💸 *Rp " . number_format($parsed['amount'], 0, ',', '.') . "* dicatat (dari {$fundName}).\n   Total hari ini: Rp {$spentF}";
            if ($remaining !== null) {
                $remF = number_format(abs($remaining), 0, ',', '.');
                $line .= $remaining >= 0 ? " | Sisa: Rp {$remF}" : " | ⚠️ Melebihi budget Rp {$remF}";
            }
            $responses[] = $line;
        }

        if (empty($responses)) {
            $this->telegram->sendMessage($chatId, 'Data tidak dapat dicatat. Coba ulangi ya!');
            return;
        }

        $this->telegram->sendMessage($chatId, "✅ Dicatat!\n\n" . implode("\n\n", $responses));
    }

    /**
     * Fix 3: After creating a low-confidence expense entry, ask which fund to use.
     */
    private function askFundSource(User $user, string $chatId, int $entryId): void
    {
        $funds = $this->funds->getFundsForUser($user);
        if ($funds->isEmpty()) return;

        $buttons = [];
        $buttons[] = ['text' => '📅 Budget Harian', 'callback_data' => "fund_src:{$entryId}:daily"];
        foreach ($funds->take(4) as $fund) {
            $buttons[] = [
                'text' => "📁 {$fund->name}",
                'callback_data' => "fund_src:{$entryId}:{$fund->id}",
            ];
        }
        $buttons[] = ['text' => '⏭️ Skip', 'callback_data' => "fund_src:{$entryId}:skip"];

        $this->telegram->sendFundSelectionKeyboard(
            $chatId,
            "💡 Uang ini dari mana?",
            $buttons
        );
    }

    /**
     * Get today's running total for a given entry type (used after auto-save).
     */
    private function getTodayTotalForType(User $user, string $type): int
    {
        return match($type) {
            'expense', 'bill_payment' => $this->entries->getTodaySpending($user),
            'meal'                    => $this->entries->getTodayCalories($user),
            'saving', 'sinking_fund_deposit' => $this->entries->getTotalSavings($user),
            'income'                  => $this->entries->getTodayIncome($user),
            default                   => 0,
        };
    }

    /**
     * Get the remaining budget/calorie for a given entry type.
     */
    private function getRemainingForType(User $user, string $type): ?int
    {
        return match($type) {
            'expense', 'bill_payment' => $this->entries->getBudgetRemaining($user),
            'meal'                    => $user->daily_calorie_goal,
            default                   => null,
        };
    }

    // ════════════════════════════════════════════════════════════════════
    // ONBOARDING CALLBACK ROUTING
    // ════════════════════════════════════════════════════════════════════

    private function handleOnboardingCallback(User $user, string $chatId, string $callbackQueryId, string $data, int $messageId): void
    {
        // Format: onboard:mode:finance | onboard:fund:emergency | onboard:fund:skip
        $parts = explode(':', $data);
        $section = $parts[1] ?? '';
        $value = $parts[2] ?? '';

        $this->telegram->answerCallbackQuery($callbackQueryId);

        match ($section) {
            'mode' => $this->onboarding->handleModeCallback($user, $chatId, $value),
            'fund' => $this->onboarding->handleFundSelectionCallback($user, $chatId, $value, $messageId),
            default => null,
        };
    }

    // ════════════════════════════════════════════════════════════════════
    // ENTRY CALLBACK HANDLERS
    // ════════════════════════════════════════════════════════════════════

    private function confirmEntry(User $user, string $chatId, string $callbackQueryId, Entry $entry, int $messageId): void
    {
        $this->entries->confirmEntry($entry);
        $this->streaks->updateAfterConfirmation($user, $entry->type);
        $this->applyFundEffect($user, $entry);
        $this->applyBillDebtEffect($user, $entry);

        $todayTotal = match ($entry->type) {
            'expense', 'bill_payment'          => $this->entries->getTodaySpending($user),
            'meal'                             => $this->entries->getTodayCalories($user),
            'saving', 'sinking_fund_deposit'   => $this->entries->getTotalSavings($user),
            'income'                           => $this->entries->getTodayIncome($user),
            default                            => 0,
        };

        $remaining = match ($entry->type) {
            'expense', 'bill_payment' => $this->entries->getBudgetRemaining($user),
            'meal'                    => $user->daily_calorie_goal,
            default                   => null,
        };

        $parsed       = $this->entryToParsedArray($entry);
        $confirmedMsg = $this->telegram->formatConfirmedMessage($entry->type, $parsed, $todayTotal, $remaining);

        // v2.1: Edit the pending confirmation message to the confirmed state + undo button
        $undoToken = Str::random(16);
        $entry->update([
            'undo_token'       => $undoToken,
            'undo_expires_at'  => now()->addMinutes(config('butler.undo_window_minutes', 5)),
        ]);

        // Edit the original message to confirmed + [↩ Undo] button
        $this->telegram->editMessageWithKeyboard(
            $chatId,
            $messageId,
            $confirmedMsg,
            [[$this->telegram->buildUndoButton($undoToken)]]
        );
        $entry->update(['telegram_message_id' => $messageId]);

        // Dispatch behavioral memory update (queue: low)
        UpdateBehavioralMemory::dispatch($entry->id, $chatId)->onQueue('low');

        $this->telegram->answerCallbackQuery($callbackQueryId, 'Dicatat.');
    }

    private function editEntry(string $chatId, string $callbackQueryId, Entry $entry, int $messageId): void
    {
        $this->entries->cancelEntry($entry);
        $this->telegram->editMessage($chatId, $messageId,
            "✏️ Oke, coba ketik ulang ya!\nContoh: `makan siang 35k` atau `grab 23rb`"
        );
        $this->telegram->answerCallbackQuery($callbackQueryId, '✏️ Silakan ketik ulang.');
    }

    private function cancelEntry(string $chatId, string $callbackQueryId, Entry $entry, int $messageId): void
    {
        $this->entries->cancelEntry($entry);
        $this->telegram->editMessage($chatId, $messageId, '❌ Dibatalkan. Tidak ada yang disimpan.');
        $this->telegram->answerCallbackQuery($callbackQueryId, '❌ Dibatalkan.');
    }

    // ════════════════════════════════════════════════════════════════════
    // FUND & BILL/DEBT EFFECTS AFTER CONFIRMATION
    // ════════════════════════════════════════════════════════════════════

    private function applyFundEffect(User $user, Entry $entry): void
    {
        try {
            $metadata = is_string($entry->metadata) ? json_decode($entry->metadata, true) : ($entry->metadata ?? []);
            
            switch ($entry->type) {
                case 'expense':
                    // Debit the source fund or fallback to default spending fund
                    $fund = null;
                    if (!empty($metadata['fund_name'])) {
                        $fund = $this->funds->findFundByName($user, $metadata['fund_name']);
                    }
                    if (!$fund && $entry->source_fund_id) {
                        $fund = \App\Models\Fund::find($entry->source_fund_id);
                    }
                    if (!$fund) {
                        $fund = $user->getDefaultSpendingFund();
                    }
                    
                    if ($fund) {
                        $this->funds->debitFund($fund, $entry->amount, $entry->id, $entry->note);
                        if (!$entry->source_fund_id || $entry->source_fund_id !== $fund->id) {
                            $entry->update([
                                'source_fund_id' => $fund->id,
                                'source_fund_confirmed' => true
                            ]);
                        }
                    }
                    break;

                case 'income':
                    // Credit the requested fund or fallback to Master Savings Fund (Tabungan)
                    $fund = null;
                    if (!empty($metadata['fund_name'])) {
                        $fund = $this->funds->findFundByName($user, $metadata['fund_name']);
                    }
                    if (!$fund) {
                        $fund = \App\Models\Fund::forUser($user->id)->where('fund_type', 'savings')->first();
                    }
                    if (!$fund) {
                        $fund = $user->getDefaultSpendingFund();
                    }
                    if ($fund) {
                        $this->funds->creditFund($fund, $entry->amount, $entry->id, $entry->note);
                    }
                    break;

                case 'saving':
                    // Credit the fund referenced in metadata/note, or default savings
                    $fundName = $metadata['fund_name'] ?? $entry->note;
                    $fund = null;
                    if ($fundName) {
                        $fund = $this->funds->findFundByName($user, $fundName);
                    }
                    if (!$fund) {
                        $fund = \App\Models\Fund::forUser($user->id)->where('fund_type', 'savings')->first();
                    }
                    if (!$fund) {
                        // Fallback to create a "Tabungan" fund
                        $fund = $this->funds->createFund($user, [
                            'fund_type' => 'savings',
                            'name' => 'Tabungan',
                            'initial_balance' => 0,
                        ]);
                    }
                    if ($fund) {
                        $this->funds->creditFund($fund, $entry->amount, $entry->id, 'User deposit');
                    }
                    break;

                case 'sinking_fund_deposit':
                    // Debit the source fund
                    $sourceFund = null;
                    if ($entry->source_fund_id) {
                        $sourceFund = \App\Models\Fund::find($entry->source_fund_id);
                    }
                    if (!$sourceFund) {
                        $sourceFund = $user->getDefaultSpendingFund();
                    }
                    if ($sourceFund) {
                        $this->funds->debitFund($sourceFund, $entry->amount, $entry->id, 'Setoran Sinking Fund');
                        if (!$entry->source_fund_id || $entry->source_fund_id !== $sourceFund->id) {
                            $entry->update(['source_fund_id' => $sourceFund->id, 'source_fund_confirmed' => true]);
                        }
                    }

                    // Credit the target sinking fund
                    $fundName = $metadata['fund_name'] ?? $entry->note;
                    $fund = null;
                    if ($fundName) {
                        $fund = $this->funds->findFundByName($user, $fundName);
                    }
                    if (!$fund) {
                        $fund = \App\Models\Fund::forUser($user->id)->where('fund_type', 'sinking_fund')->first();
                    }
                    if ($fund) {
                        $this->funds->creditFund($fund, $entry->amount, $entry->id, 'Setoran Sinking Fund');
                    }
                    break;

                case 'transfer':
                    $direction = $metadata['direction'] ?? 'internal';

                    // Prefer resolved IDs (set when the user picked an account); fall
                    // back to name lookup. NEVER silently substitute a default account —
                    // an unresolved source/target means nothing moves (handleTransfer
                    // is responsible for asking before we reach this point).
                    $sourceFund = $entry->source_fund_id
                        ? \App\Models\Fund::find($entry->source_fund_id)
                        : (!empty($metadata['source_fund']) ? $this->funds->findFundByName($user, $metadata['source_fund']) : null);

                    $targetFund = !empty($metadata['target_fund_id'])
                        ? \App\Models\Fund::find($metadata['target_fund_id'])
                        : (!empty($metadata['target_fund']) ? $this->funds->findFundByName($user, $metadata['target_fund']) : null);

                    if ($direction === 'in') {
                        if ($targetFund) {
                            $this->funds->creditFund($targetFund, $entry->amount, $entry->id, 'Transfer masuk');
                        }
                    } elseif ($direction === 'out') {
                        if ($sourceFund) {
                            $this->funds->debitFund($sourceFund, $entry->amount, $entry->id, 'Transfer keluar');
                        }
                    } else { // internal
                        if ($sourceFund && $targetFund && $sourceFund->id !== $targetFund->id) {
                            $this->funds->debitFund($sourceFund, $entry->amount, $entry->id, "Transfer ke {$targetFund->name}");
                            $this->funds->creditFund($targetFund, $entry->amount, $entry->id, "Transfer dari {$sourceFund->name}");
                        }
                    }
                    break;
            }
        } catch (\Exception $e) {
            Log::error('applyFundEffect failed', ['entry_id' => $entry->id, 'error' => $e->getMessage()]);
        }
    }

    private function applyBillDebtEffect(User $user, Entry $entry): void
    {
        try {
            if ($entry->type === 'bill_payment' && $entry->merchant) {
                $bill = $this->bills->findMatchingBill($user, $entry->merchant);
                if ($bill) {
                    $this->bills->markPaid($bill, $entry->amount);
                    $user->update(['has_bills_setup' => true]);

                    // Debit the appropriate fund
                    $fund = $bill->source_fund_id
                        ? \App\Models\Fund::find($bill->source_fund_id)
                        : $user->getDefaultSpendingFund();
                    if ($fund) {
                        $this->funds->debitFund($fund, $entry->amount, $entry->id, "Bayar Tagihan: {$bill->name}");
                        $entry->update([
                            'source_fund_id' => $fund->id,
                            'source_fund_confirmed' => true
                        ]);
                    }
                }
            }

            if ($entry->type === 'debt_payment' && $entry->note) {
                $debt = $this->debts->findMatchingDebt($user, $entry->note);
                if ($debt) {
                    $this->debts->recordPayment($debt, $entry->amount);
                    $user->update(['has_debt_declared' => true]);

                    // Debit default spending fund
                    $fund = $user->getDefaultSpendingFund();
                    if ($fund) {
                        $this->funds->debitFund($fund, $entry->amount, $entry->id, "Bayar Cicilan: {$debt->name}");
                        $entry->update([
                            'source_fund_id' => $fund->id,
                            'source_fund_confirmed' => true
                        ]);
                    }
                }
            }
        } catch (\Exception $e) {
            Log::error('applyBillDebtEffect failed', ['entry_id' => $entry->id, 'error' => $e->getMessage()]);
        }
    }

    // ════════════════════════════════════════════════════════════════════
    // ACTION HANDLERS
    // ════════════════════════════════════════════════════════════════════

    private function handleAddBill(User $user, string $chatId, array $parsed): void
    {
        if (!isset($parsed['amount']) || !isset($parsed['due_day'])) {
            $this->telegram->sendMessage($chatId,
                "Butler butuh: nama tagihan, nominal, dan tanggal jatuh tempo.\n"
                . "Contoh: \"tambahin tagihan Netflix 65rb tiap tanggal 15\""
            );
            return;
        }

        try {
            $bill = $this->bills->createBill($user, [
                'name' => $parsed['name'] ?? 'Tagihan',
                'amount' => $parsed['amount'],
                'due_day' => $parsed['due_day'],
            ]);

            $amountF = 'Rp ' . number_format($bill->amount, 0, ',', '.');
            $this->telegram->sendMessage($chatId,
                "✅ *Tagihan ditambahkan!*\n\n"
                . "📋 {$bill->name}\n"
                . "💰 {$amountF}/bulan\n"
                . "📅 Jatuh tempo: tanggal {$bill->due_day}\n\n"
                . "Butler akan ingatkan 3 hari sebelum jatuh tempo."
            );
        } catch (\Exception $e) {
            $this->telegram->sendMessage($chatId, 'Gagal menyimpan tagihan. Coba lagi ya!');
        }
    }

    private function handleAddSinkingFund(User $user, string $chatId, array $parsed): void
    {
        try {
            $fund = $this->funds->createFund($user, [
                'fund_type' => 'sinking_fund',
                'name' => $parsed['name'] ?? 'Sinking Fund',
                'target_amount' => $parsed['target_amount'] ?? null,
                'target_date' => $parsed['target_date'] ?? null,
            ]);

            $msg = "✅ *Sinking fund dibuat!*\n\n📁 {$fund->name}";
            if ($fund->target_amount) {
                $targetF = 'Rp ' . number_format($fund->target_amount, 0, ',', '.');
                $msg .= "\n🎯 Target: {$targetF}";
            }
            if ($fund->target_date) {
                $msg .= "\n📅 Deadline: " . $fund->target_date->format('d M Y');
            }
            $msg .= "\n\nAyo mulai isi dana ini! Ketik: \"nabung 200k ke {$fund->name}\"";

            $this->telegram->sendMessage($chatId, $msg);
        } catch (\Exception $e) {
            $this->telegram->sendMessage($chatId, 'Gagal membuat sinking fund. Coba lagi ya!');
        }
    }

    private function handleQueryBalance(User $user, string $chatId, array $parsed): void
    {
        $queryTarget = $parsed['query_target'] ?? 'total_savings';
        $fundName = $parsed['fund_name'] ?? null;

        if ($fundName) {
            $fund = $this->funds->findFundByName($user, $fundName);
            if ($fund) {
                $balF = 'Rp ' . number_format($fund->current_balance, 0, ',', '.');
                $msg = "💰 *{$fund->name}*\nSaldo: {$balF}";
                if ($fund->target_amount) {
                    $targetF = 'Rp ' . number_format($fund->target_amount, 0, ',', '.');
                    $msg .= "\n🎯 Target: {$targetF} ({$fund->progress_pct}%)";
                    if ($fund->on_track !== null) {
                        $msg .= $fund->on_track ? "\n✅ On track!" : "\n⚠️ Perlu tambah setoran!";
                    }
                }
                $this->telegram->sendMessage($chatId, $msg);
                return;
            }
            // Fund not found
            $this->telegram->sendMessage($chatId,
                "Dana '{$fundName}' belum ada. Mau Butler buatin? Ketik: \"buat sinking fund {$fundName}\""
            );
            return;
        }

        // ── Summary of all funds ────────────────────────────────────────
        $allFunds = $this->funds->getFundsForUser($user);
        $grandTotal = $allFunds->sum('current_balance');
        $grandTotalF = number_format($grandTotal, 0, ',', '.');

        $msg = "💰 *Total Semua Uang: Rp {$grandTotalF}*\n";
        $msg .= "──────────────\n";

        // Spending budget section — show with today's context
        if ($user->daily_budget_idr) {
            $todaySpent = $this->entries->getTodaySpending($user);
            $remaining  = $this->entries->getBudgetRemaining($user);
            $budgetF    = number_format($user->daily_budget_idr, 0, ',', '.');
            $spentF     = number_format($todaySpent, 0, ',', '.');
            $remF       = number_format(abs($remaining ?? 0), 0, ',', '.');
            $msg .= "\n📅 *Budget Harian*\n";
            $msg .= "   Limit: Rp {$budgetF}\n";
            $msg .= "   Terpakai hari ini: Rp {$spentF}\n";
            if ($remaining !== null) {
                $msg .= $remaining >= 0
                    ? "   ✅ Sisa: Rp {$remF}\n"
                    : "   ⚠️ Melebihi budget: Rp {$remF}\n";
            }
        }

        // Group funds by type
        $typeGroups = [
            'emergency_fund' => ['label' => '🛡️ Dana Darurat', 'funds' => []],
            'savings'        => ['label' => '📈 Tabungan/Invest', 'funds' => []],
            'sinking_fund'   => ['label' => '✈️ Sinking Fund', 'funds' => []],
            'financial_goal' => ['label' => '🎯 Financial Goal', 'funds' => []],
        ];

        foreach ($allFunds as $fund) {
            if ($fund->is_default_spending) continue; // already shown as budget
            if (isset($typeGroups[$fund->fund_type])) {
                $typeGroups[$fund->fund_type]['funds'][] = $fund;
            }
        }

        foreach ($typeGroups as $group) {
            if (empty($group['funds'])) continue;
            $msg .= "\n{$group['label']}\n";
            foreach ($group['funds'] as $fund) {
                $balF = number_format($fund->current_balance, 0, ',', '.');
                $msg .= "   • {$fund->name}: Rp {$balF}";
                if ($fund->target_amount) {
                    $pct = $fund->progress_pct;
                    $msg .= " ({$pct}%)";
                    if ($fund->on_track !== null) {
                        $msg .= $fund->on_track ? " ✅" : " ⚠️";
                    }
                }
                $msg .= "\n";
            }
        }

        // Bills section
        $bills = $user->bills()->active()->get();
        if ($bills->isNotEmpty()) {
            $msg .= "\n🧾 *Tagihan Tetap*\n";
            foreach ($bills as $bill) {
                $billF = number_format($bill->amount, 0, ',', '.');
                $paid  = $bill->this_month_paid ? ' ✅' : " (tgl {$bill->due_day})";
                $msg .= "   • {$bill->name}: Rp {$billF}{$paid}\n";
            }
        }

        // Debts section
        $debts = $user->debts()->active()->get();
        if ($debts->isNotEmpty()) {
            $msg .= "\n💳 *Cicilan Aktif*\n";
            foreach ($debts as $debt) {
                $installF = number_format($debt->monthly_installment, 0, ',', '.');
                $remainB  = number_format($debt->remaining_balance, 0, ',', '.');
                $msg .= "   • {$debt->name}: Rp {$installF}/bln (sisa Rp {$remainB})\n";
            }
        }

        if ($allFunds->isEmpty() && !$user->daily_budget_idr && $bills->isEmpty() && $debts->isEmpty()) {
            $msg .= "\n_Belum ada data keuangan. Coba catat income atau pengeluaran pertama kamu!_";
        }

        $this->telegram->sendMessage($chatId, $msg);
    }


    private function handleQuerySpending(User $user, string $chatId, array $parsed): void
    {
        $period = $parsed['period'] ?? 'today';

        if ($period === 'month') {
            $total = $this->entries->getMonthSpending($user);
            $response = $this->telegram->formatSpendingTodayResponse($total);
            $this->telegram->sendMessage($chatId, str_replace('hari ini', 'bulan ini', $response));
        } else {
            $total = $this->entries->getTodaySpending($user);
            $remaining = $this->entries->getBudgetRemaining($user);
            $this->telegram->sendMessage($chatId,
                $this->telegram->formatSpendingTodayResponse($total, $remaining)
            );
        }
    }

    private function handleSetReminder(User $user, string $chatId, array $parsed): void
    {
        try {
            \App\Models\Reminder::create([
                'user_id' => $user->id,
                'type' => 'time_based',
                'trigger_time' => $parsed['trigger_time'] ?? '20:00',
                'trigger_days' => $parsed['trigger_days'] ?? 'mon,tue,wed,thu,fri,sat,sun',
                'message_template' => $parsed['reminder_text'] ?? 'Pengingat dari Butler!',
                'is_system' => false,
            ]);

            $time = $parsed['trigger_time'] ?? '20:00';
            $this->telegram->sendMessage($chatId, "✅ Pengingat di-set untuk jam {$time}!");
        } catch (\Exception $e) {
            $this->telegram->sendMessage($chatId, 'Gagal set pengingat. Coba lagi ya!');
        }
    }

    // ════════════════════════════════════════════════════════════════════
    // FUND TRANSFER (direction-aware: in / out / internal)
    // ════════════════════════════════════════════════════════════════════

    /**
     * Handle a money transfer. Source and target are always the user's own
     * money-holding funds (accounts/wallets/buckets). Direction determines
     * which legs move:
     *   - in       → money received: credit target only
     *   - out      → money sent out:  debit source only
     *   - internal → between own funds: debit source + credit target
     *
     * The source account is never silently assumed. If it isn't stated, we
     * consult the learned "transfer_source" memory; if that's still unknown,
     * we ask the user and remember their answer for next time.
     */
    private function handleTransfer(User $user, string $chatId, string $rawMessage, array $parsed, float $confidence): void
    {
        $amount = (int) ($parsed['amount'] ?? 0);
        if ($amount <= 0) {
            $this->telegram->sendMessage($chatId, "Nominal transfernya berapa? Contoh: `transfer 100k ke BCA`");
            return;
        }

        $direction  = in_array($parsed['direction'] ?? '', ['in', 'out', 'internal'], true)
            ? $parsed['direction']
            : 'internal';
        $sourceFund = !empty($parsed['source_fund']) ? $this->funds->findFundByName($user, $parsed['source_fund']) : null;
        $targetFund = !empty($parsed['target_fund']) ? $this->funds->findFundByName($user, $parsed['target_fund']) : null;

        // ── Incoming: money received into one of the user's accounts ──────
        if ($direction === 'in') {
            if (!$targetFund) {
                $entry = $this->createTransferEntry($user, $rawMessage, $parsed, 'in', null, null);
                $this->askTransferAccount($user, $chatId, $entry->id, 'tgt', '📥 Diterima ke akun mana?');
                return;
            }
            $entry = $this->createTransferEntry($user, $rawMessage, $parsed, 'in', null, $targetFund);
            $this->finishTransfer($user, $chatId, $entry, $confidence);
            return;
        }

        // ── Outgoing: money sent out from one of the user's wallets ───────
        if ($direction === 'out') {
            $sourceFund = $sourceFund ?? $this->resolveLearnedTransferSource($user);
            if (!$sourceFund) {
                $entry = $this->createTransferEntry($user, $rawMessage, $parsed, 'out', null, null);
                $this->askTransferAccount($user, $chatId, $entry->id, 'src', '📤 Transfer ini dari akun mana?');
                return;
            }
            $entry = $this->createTransferEntry($user, $rawMessage, $parsed, 'out', $sourceFund, null);
            $this->learnTransferSource($user, $sourceFund);
            $this->finishTransfer($user, $chatId, $entry, $confidence);
            return;
        }

        // ── Internal: between the user's own accounts/buckets ─────────────
        if (!$targetFund) {
            $this->telegram->sendMessage(
                $chatId,
                "Mau dipindah ke mana? Contoh: `transfer 100k ke BCA` atau `pindahin 200k ke Dana Darurat`"
            );
            return;
        }

        if (!$sourceFund) {
            $sourceFund = $this->resolveLearnedTransferSource($user, $targetFund);
        }

        if (!$sourceFund) {
            // Don't assume the main account — ask, and learn the answer.
            $entry = $this->createTransferEntry($user, $rawMessage, $parsed, 'internal', null, $targetFund);
            $this->askTransferAccount($user, $chatId, $entry->id, 'src', "📤 Dari akun mana ke *{$targetFund->name}*?", $targetFund->id);
            return;
        }

        if ($sourceFund->id === $targetFund->id) {
            $this->telegram->sendMessage($chatId, "Akun asal dan tujuan sama. Sebut akun yang berbeda ya!");
            return;
        }

        $entry = $this->createTransferEntry($user, $rawMessage, $parsed, 'internal', $sourceFund, $targetFund);
        $this->learnTransferSource($user, $sourceFund);
        $this->finishTransfer($user, $chatId, $entry, $confidence);
    }

    /**
     * Create the pending transfer entry with resolved fund references.
     */
    private function createTransferEntry(User $user, string $rawMessage, array $parsed, string $direction, ?Fund $source, ?Fund $target): Entry
    {
        $payload = [
            'intent'      => 'transfer_fund',
            'confidence'  => $parsed['confidence'] ?? 0.9,
            'amount'      => (int) ($parsed['amount'] ?? 0),
            'direction'   => $direction,
            'source_fund' => $source?->name ?? ($parsed['source_fund'] ?? null),
            'target_fund' => $target?->name ?? ($parsed['target_fund'] ?? null),
            'entry_time'  => $parsed['entry_time'] ?? null,
        ];
        if ($source) $payload['source_fund_id'] = $source->id;
        if ($target) $payload['target_fund_id'] = $target->id;

        return $this->entries->createPendingEntry($user, $payload, $rawMessage);
    }

    /**
     * Confirm a fully-resolved transfer: immediately for high confidence,
     * otherwise behind a confirmation keyboard.
     */
    private function finishTransfer(User $user, string $chatId, Entry $entry, float $confidence): void
    {
        if ($confidence >= 0.85) {
            $this->confirmAndSendWithUndo($user, $chatId, $entry);
            return;
        }

        $parsed  = $this->entryToParsedArray($entry);
        $text    = $this->telegram->formatTransferConfirmation($parsed, $confidence);
        $buttons = $this->telegram->buildConfirmationButtons($entry->id);
        $this->telegram->sendMessageWithInlineKeyboard($chatId, $text, $buttons);
    }

    /**
     * Look up the account the user usually transfers FROM (learned over time).
     * Returns null when nothing confident enough is known — caller should ask.
     */
    private function resolveLearnedTransferSource(User $user, ?Fund $exclude = null): ?Fund
    {
        $mem = $this->memory->resolve($user->id, \App\Models\BehavioralMemory::DOMAIN_TRANSFER_SOURCE, 'default');
        $fundId = $mem?->value['account_id'] ?? null;
        if (!$fundId) {
            return null;
        }

        $fund = Fund::forUser($user->id)->active()->find($fundId);
        if ($fund && $exclude && $fund->id === $exclude->id) {
            return null; // a fund can't be its own source
        }
        return $fund;
    }

    /**
     * Strengthen the learned "which account do I transfer from" memory.
     */
    private function learnTransferSource(User $user, Fund $source): void
    {
        $this->memory->observe($user->id, \App\Models\BehavioralMemory::DOMAIN_TRANSFER_SOURCE, 'default', [
            'account_id'   => $source->id,
            'account_name' => $source->name,
        ]);
    }

    /**
     * Ask the user to pick the source/target account for a pending transfer.
     * Only the user's real money-holding funds are offered.
     */
    private function askTransferAccount(User $user, string $chatId, int $entryId, string $role, string $prompt, ?int $excludeFundId = null): void
    {
        $funds = $this->funds->getFundsForUser($user);
        if ($excludeFundId) {
            $funds = $funds->where('id', '!=', $excludeFundId);
        }

        if ($funds->isEmpty()) {
            $this->telegram->sendMessage($chatId, 'Belum ada akun/dompet tersimpan. Tambah dulu lewat dashboard ya.');
            return;
        }

        $accounts = $funds->map(fn ($f) => ['id' => $f->id, 'name' => $f->name])->values()->toArray();
        $this->telegram->sendTransferAccountKeyboard($chatId, $entryId, $role, $accounts, $prompt);
    }

    /**
     * Handle the account pick for a pending transfer.
     * Format: xfer_pick:{entryId}:{role(src|tgt)}:{fundId}
     */
    private function handleTransferPickCallback(User $user, string $chatId, string $callbackQueryId, string $data, int $messageId): void
    {
        $parts   = explode(':', $data);
        $entryId = (int) ($parts[1] ?? 0);
        $role    = $parts[2] ?? '';
        $fundId  = (int) ($parts[3] ?? 0);

        $this->telegram->answerCallbackQuery($callbackQueryId);

        $entry = Entry::forUser($user->id)->pending()->where('type', 'transfer')->find($entryId);
        $fund  = $fundId ? Fund::forUser($user->id)->find($fundId) : null;

        if (!$entry || !$fund) {
            $this->telegram->editMessage($chatId, $messageId, 'Transfer tidak ditemukan atau sudah diproses.');
            return;
        }

        $metadata = $entry->metadata ?? [];

        if ($role === 'src') {
            $entry->update(['source_fund_id' => $fund->id, 'source_fund_confirmed' => true]);
            $metadata['source_fund'] = $fund->name;
            $entry->update(['metadata' => $metadata]);
            $this->learnTransferSource($user, $fund);
        } elseif ($role === 'tgt') {
            $metadata['target_fund']    = $fund->name;
            $metadata['target_fund_id'] = $fund->id;
            $entry->update(['metadata' => $metadata]);
        }

        // Guard against a self-transfer once both legs are known.
        $srcId = $entry->fresh()->source_fund_id;
        $tgtId = ($entry->fresh()->metadata['target_fund_id'] ?? null);
        if ($srcId && $tgtId && (int) $srcId === (int) $tgtId) {
            $this->entries->cancelEntry($entry);
            $this->telegram->editMessage($chatId, $messageId, 'Akun asal dan tujuan sama — transfer dibatalkan.');
            return;
        }

        $this->telegram->editMessage($chatId, $messageId, "Oke, pakai *{$fund->name}*.");
        $this->confirmAndSendWithUndo($user, $chatId, $entry->fresh());
    }

    // ════════════════════════════════════════════════════════════════════
    // RESPONSE BUILDERS
    // ════════════════════════════════════════════════════════════════════

    // ════════════════════════════════════════════════════════════════════
    // DEVICE PAIRING
    // ════════════════════════════════════════════════════════════════════

    private function sendPairIphoneInstructions(User $user, string $chatId): void
    {
        $code    = $this->pairing->generateCode($user);
        $text    = $this->pairing->buildPairingMessage($code);
        $buttons = $this->pairing->buildPairingButtons($code);

        $this->telegram->sendMessageWithInlineKeyboard($chatId, $text, $buttons);
    }

    private function sendDevicesList(User $user, string $chatId): void
    {
        $text    = $this->pairing->buildDevicesMessage($user);
        $buttons = $this->pairing->buildDevicesButtons($user);

        if (empty($buttons)) {
            $this->telegram->sendMessage($chatId, $text);
            return;
        }

        // Each button on its own row so names don't get truncated
        $rows = array_map(fn ($b) => [$b], $buttons);

        $keyboard = ['inline_keyboard' => $rows];

        try {
            \Illuminate\Support\Facades\Http::post(
                "https://api.telegram.org/bot" . config('butler.telegram.bot_token') . "/sendMessage",
                [
                    'chat_id'      => $chatId,
                    'text'         => $text,
                    'parse_mode'   => 'Markdown',
                    'reply_markup' => json_encode($keyboard),
                ]
            );
        } catch (\Exception $e) {
            Log::error('sendDevicesList failed', ['message' => $e->getMessage()]);
            $this->telegram->sendMessage($chatId, $text);
        }
    }

    private function handlePairDeviceCallback(
        User   $user,
        string $chatId,
        string $callbackQueryId,
        string $data,
        int    $messageId
    ): void {
        // pair_device:cancel:{pairingCodeId}
        // pair_device:revoke:{deviceId}
        // pair_device:new
        $parts  = explode(':', $data);
        $action = $parts[1] ?? '';

        $this->telegram->answerCallbackQuery($callbackQueryId);

        if ($action === 'cancel') {
            $this->telegram->editMessage($chatId, $messageId, '❌ Pairing dibatalkan.');
            return;
        }

        if ($action === 'revoke') {
            $deviceId = (int) ($parts[2] ?? 0);
            $success  = $this->pairing->revokeDevice($user, $deviceId);

            if ($success) {
                // Refresh the device list in-place
                $newText    = $this->pairing->buildDevicesMessage($user);
                $newButtons = $this->pairing->buildDevicesButtons($user);

                if (empty($newButtons)) {
                    $this->telegram->editMessage($chatId, $messageId, $newText);
                    return;
                }

                $rows     = array_map(fn ($b) => [$b], $newButtons);
                $keyboard = ['inline_keyboard' => $rows];

                try {
                    \Illuminate\Support\Facades\Http::post(
                        "https://api.telegram.org/bot" . config('butler.telegram.bot_token') . "/editMessageText",
                        [
                            'chat_id'      => $chatId,
                            'message_id'   => $messageId,
                            'text'         => $newText,
                            'parse_mode'   => 'Markdown',
                            'reply_markup' => json_encode($keyboard),
                        ]
                    );
                } catch (\Exception $e) {
                    Log::error('handlePairDeviceCallback revoke edit failed', ['message' => $e->getMessage()]);
                }
            } else {
                $this->telegram->answerCallbackQuery($callbackQueryId, 'Perangkat tidak ditemukan.');
            }

            return;
        }

        if ($action === 'new') {
            $this->telegram->editMessage($chatId, $messageId, '');
            $this->sendPairIphoneInstructions($user, $chatId);
            return;
        }
    }

    private function sendHelp(User $user, string $chatId): void
    {
        $help = "🤖 *Butler - Asisten Harianmu*\n\n"
            . "Kirim pesan biasa, Butler otomatis ngerti!\n\n";

        if ($user->isFinanceMode()) {
            $help .= "💸 *Pengeluaran:* `makan nasi goreng 35k`\n"
                . "💰 *Income:* `gajian 5jt`\n"
                . "🧾 *Tagihan:* `bayar kos 1.5jt`\n"
                . "💳 *Cicilan:* `bayar cicilan motor 800rb`\n"
                . "💎 *Tabungan:* `nabung 500rb ke dana darurat`\n"
                . "✈️ *Sinking Fund:* `masukin 200k ke nabung liburan`\n\n";
        }

        if ($user->isCalorieMode()) {
            $help .= "🍽️ *Makanan:* `makan nasi goreng`\n";
            $help .= "😊 *Mood:* `mood: good, energi 4`\n\n";
        }

        $help .= "📊 *Cek Data:*\n"
            . "• `summary` — ringkasan hari ini\n"
            . "• `saldo` — semua dana & budget\n"
            . "• `tagihan` — daftar tagihan\n"
            . "• `buka dashboard` — webview lengkap\n"
            . "• `settings` — ubah profil & tujuan\n\n";

        // Add today's live snapshot so help is actually useful
        if ($user->isFinanceMode()) {
            $spent     = $this->entries->getTodaySpending($user);
            $remaining = $this->entries->getBudgetRemaining($user);
            $spentF    = number_format($spent, 0, ',', '.');
            $help     .= "📅 *Hari ini:* Rp {$spentF} terpakai";
            if ($remaining !== null) {
                $remF  = number_format(abs($remaining), 0, ',', '.');
                $help .= $remaining >= 0 ? " · sisa Rp {$remF}" : " · ⚠️ lebih Rp {$remF}";
            }
            $help .= "\n";
        }

        if ($user->isCalorieMode()) {
            $cal  = $this->entries->getTodayCalories($user);
            $goal = $user->daily_calorie_goal;
            if ($cal > 0 || $goal) {
                $help .= "🔥 *Kalori:* {$cal}";
                if ($goal) $help .= "/{$goal}";
                $help .= " kcal\n";
            }
        }

        $streak = $user->streak;
        if ($streak && $streak->log_current > 0) {
            $help .= "🔥 *Streak:* {$streak->log_current} hari";
        }

        $this->telegram->sendMessage($chatId, $help);
    }

    private function sendBillList(User $user, string $chatId): void
    {
        $bills = $user->bills()->active()->orderBy('due_day')->get();

        if ($bills->isEmpty()) {
            $this->telegram->sendMessage($chatId,
                "Belum ada tagihan tersimpan.\nTambah dengan: `tambahin tagihan Netflix 65rb tgl 15`"
            );
            return;
        }

        $msg  = "🧾 *Tagihan Tetap*\n──────────────\n";
        $today = now()->day;

        foreach ($bills as $bill) {
            $amountF = number_format($bill->amount, 0, ',', '.');
            $daysLeft = $bill->due_day >= $today
                ? $bill->due_day - $today
                : (cal_days_in_month(CAL_GREGORIAN, now()->month, now()->year) - $today + $bill->due_day);

            $paid  = $bill->this_month_paid ? ' ✅' : '';
            $alert = (!$bill->this_month_paid && $daysLeft <= 3) ? " ⚠️ {$daysLeft}h lagi" : '';

            $msg .= "• *{$bill->name}*: Rp {$amountF}/bln (tgl {$bill->due_day}){$paid}{$alert}\n";
        }

        $totalF = number_format($bills->sum('amount'), 0, ',', '.');
        $msg   .= "──────────────\nTotal: Rp {$totalF}/bulan";

        $this->telegram->sendMessage($chatId, $msg);
    }

    private function sendSettingsLink(User $user, string $chatId): void
    {
        $url = URL::temporarySignedRoute(
            'dashboard.auth',
            now()->addMinutes(30),
            ['telegram_id' => $user->telegram_chat_id]
        );

        $this->telegram->sendMessageWithInlineKeyboard(
            $chatId,
            "Buka dashboard untuk ubah profil, budget, dan tujuanmu (berlaku 30 menit):",
            [['text' => '⚙️ Buka Settings', 'url' => $url]]
        );
    }

    private function handleMoodLog(User $user, string $chatId, string $rawMessage): bool
    {
        // Parse: "mood: good" / "mood: bagus, energi 4" / "mood good energi 3"
        $text = strtolower(trim($rawMessage));

        // Strip the "mood:" or "mood " prefix
        $payload = preg_replace('/^mood\s*:?\s*/i', '', $text);

        // Extract energy level if present: "energi 4" or "energy 4"
        $energy = null;
        if (preg_match('/energi\s+([1-5])|energy\s+([1-5])/i', $payload, $m)) {
            $energy  = (int) ($m[1] ?: $m[2]);
            $payload = preg_replace('/,?\s*(energi|energy)\s+[1-5]/i', '', $payload);
        }

        // Extract note after comma: "mood: good, capek banget"
        $note    = null;
        $parts   = explode(',', $payload, 2);
        $moodStr = trim($parts[0]);
        if (isset($parts[1])) {
            $noteCandidate = trim($parts[1]);
            // only treat as note if it's not the energy token we already consumed
            if (!preg_match('/^energi|^energy/i', $noteCandidate)) {
                $note = $noteCandidate ?: null;
            }
        }

        $mood = MoodLog::parseMood($moodStr);
        if (!$mood) {
            return false; // Let AI handle it
        }

        MoodLog::updateOrCreate(
            ['telegram_chat_id' => (string) $user->telegram_chat_id, 'log_date' => today()->toDateString()],
            ['mood' => $mood, 'energy_level' => $energy, 'note' => $note]
        );

        $moodEmoji = match ($mood) {
            'great'    => '🤩',
            'good'     => '😊',
            'okay'     => '😐',
            'bad'      => '😔',
            'terrible' => '😢',
        };

        $reply = "{$moodEmoji} Mood hari ini: *{$mood}*";
        if ($energy) $reply .= " · energi {$energy}/5";
        if ($note)   $reply .= "\n_{$note}_";

        $this->telegram->sendMessage($chatId, $reply);
        return true;
    }

    private function sendQuickSummary(User $user, string $chatId): void
    {
        $spending = $this->entries->getTodaySpending($user);
        $calories = $this->entries->getTodayCalories($user);
        $remaining = $this->entries->getBudgetRemaining($user);
        $income = $this->entries->getTodayIncome($user);
        $streak = $user->streak;

        $spendF = number_format($spending, 0, ',', '.');
        $msg = "📊 *Ringkasan Hari Ini*\n\n";

        if ($user->isFinanceMode()) {
            $msg .= "💸 Pengeluaran: Rp {$spendF}\n";
            if ($income > 0) {
                $msg .= "💰 Income: Rp " . number_format($income, 0, ',', '.') . "\n";
            }
            if ($remaining !== null) {
                $remainF = number_format(abs($remaining), 0, ',', '.');
                $msg .= $remaining >= 0
                    ? "💰 Sisa budget: Rp {$remainF}\n"
                    : "⚠️ Melebihi budget: Rp {$remainF}\n";
            }
        }

        if ($user->isCalorieMode() && $calories > 0) {
            $msg .= "🔥 Kalori: {$calories}";
            if ($user->daily_calorie_goal) {
                $msg .= "/{$user->daily_calorie_goal}";
            }
            $msg .= " kcal\n";
        }

        if ($streak && $streak->log_current > 0) {
            $msg .= "\n🔥 Streak: {$streak->log_current} hari berturut-turut!";
        }

        $this->telegram->sendMessage($chatId, $msg);
    }

    // ════════════════════════════════════════════════════════════════════
    // v2.5 — CALORIE EDIT VIA TELEGRAM
    // ════════════════════════════════════════════════════════════════════

    /**
     * Handle the [🔢 Edit Kalori] button tap.
     * Stores the entry ID in cache so the next text message is treated as the new calorie value.
     */
    private function handleCalorieEditCallback(User $user, string $chatId, string $callbackQueryId, string $data): void
    {
        $entryId = (int) substr($data, 9); // strip "cal_edit:"

        $entry = Entry::forUser($user->id)
            ->where('type', 'meal')
            ->where('status', 'confirmed')
            ->where('created_at', '>=', now()->subMinutes(30))
            ->find($entryId);

        if (!$entry) {
            $this->telegram->answerCallbackQuery($callbackQueryId, 'Entry tidak ditemukan atau sudah kedaluwarsa.');
            return;
        }

        // Store pending correction in cache (5-minute TTL)
        Cache::put("cal_correction:{$chatId}", $entryId, now()->addMinutes(5));

        $this->telegram->answerCallbackQuery($callbackQueryId);
        $this->telegram->sendMessage(
            $chatId,
            "🔢 Kirim jumlah kalori baru untuk *{$entry->food_item}* (sekarang: {$entry->calories} kcal):\n\n_Contoh: `350` atau `350 kcal`_"
        );
    }

    /**
     * Handle a pending calorie correction — user sends a number after tapping [🔢 Edit Kalori].
     * Returns true if this message was consumed as a correction (caller should stop).
     */
    private function handleCalorieCorrection(User $user, string $chatId, string $message): bool
    {
        $entryId = Cache::get("cal_correction:{$chatId}");
        if (!$entryId) {
            return false;
        }

        // Extract number from input: "350", "350 kcal", "350kcal"
        $cleaned = trim(preg_replace('/\s*(kcal|kkal|kalori|cal)\s*/i', '', $message));

        if (!is_numeric($cleaned) || (int) $cleaned <= 0) {
            $this->telegram->sendMessage($chatId, "Kirim angka kalori saja ya, contoh: `350`");
            return true; // Consumed the message — keep cache alive
        }

        $newCalories = (int) $cleaned;

        $entry = Entry::forUser($user->id)
            ->where('type', 'meal')
            ->where('status', 'confirmed')
            ->find($entryId);

        if (!$entry) {
            Cache::forget("cal_correction:{$chatId}");
            $this->telegram->sendMessage($chatId, 'Entry tidak ditemukan. Coba log ulang ya!');
            return true;
        }

        $oldCalories = $entry->calories;
        $entry->update(['calories' => $newCalories, 'is_calorie_estimated' => false]);

        // Update behavioral memory for this food item
        if ($entry->food_item) {
            $this->memory->observe($user->id, 'food_calories', $entry->food_item, [
                'calories' => $newCalories,
            ]);
        }

        Cache::forget("cal_correction:{$chatId}");

        $totalCal = $this->entries->getTodayCalories($user);
        $goalStr = $user->daily_calorie_goal ? "/{$user->daily_calorie_goal}" : '';

        $this->telegram->sendMessage(
            $chatId,
            "✅ Kalori *{$entry->food_item}* diupdate: {$oldCalories} → {$newCalories} kcal\n🔥 Total hari ini: {$totalCal}{$goalStr} kcal"
        );

        return true;
    }

    // ════════════════════════════════════════════════════════════════════
    // HELPERS
    // ════════════════════════════════════════════════════════════════════

    /**
     * Build grounding context for the AI parser: the user's real fund names,
     * previously-corrected food calories (from behavioral memory), and the
     * active tracking mode. Keeps extraction concrete instead of guessed.
     */
    private function buildParserContext(User $user): array
    {
        $funds = $this->funds->getFundsForUser($user)
            ->pluck('name')
            ->filter()
            ->take(15)
            ->values()
            ->toArray();

        $learnedFoods = [];
        if ($user->isCalorieMode()) {
            $parserCtx = $this->memory->getParserContext($user->id);
            foreach ($parserCtx['food_calories'] ?? [] as $row) {
                $subject  = $row['subject'] ?? null;
                $calories = $row['value']['calories'] ?? null;
                if ($subject && $calories) {
                    $learnedFoods[$subject] = (int) $calories;
                }
            }
        }

        $mode = match (true) {
            $user->isFinanceMode() && $user->isCalorieMode() => 'both',
            $user->isCalorieMode()                           => 'calorie',
            default                                          => 'finance',
        };

        return [
            'funds'         => $funds,
            'learned_foods' => $learnedFoods,
            'mode'          => $mode,
        ];
    }

    private function entryToParsedArray(Entry $entry): array
    {
        return match ($entry->type) {
            'expense'              => ['amount' => $entry->amount, 'category' => $entry->category, 'merchant' => $entry->merchant, 'note' => $entry->note],
            'meal'                 => ['food_item' => $entry->food_item, 'calories' => $entry->calories, 'is_calorie_estimated' => $entry->is_calorie_estimated],
            'saving'               => ['amount' => $entry->amount, 'note' => $entry->note, 'fund_name' => $entry->note],
            'income'               => ['amount' => $entry->amount, 'source' => 'gaji', 'note' => $entry->note],
            'bill_payment'         => ['amount' => $entry->amount, 'bill_name' => $entry->merchant ?? $entry->note],
            'debt_payment'         => ['amount' => $entry->amount, 'debt_name' => $entry->note],
            'sinking_fund_deposit' => ['amount' => $entry->amount, 'fund_name' => $entry->note],
            'transfer'             => [
                'amount'      => $entry->amount,
                'direction'   => $entry->metadata['direction'] ?? 'internal',
                'source_fund' => $entry->sourceFund?->name ?? ($entry->metadata['source_fund'] ?? null),
                'target_fund' => $entry->metadata['target_fund'] ?? null,
            ],
            default                => [],
        };
    }

    // ════════════════════════════════════════════════════════════════════
    // v2.1 — RESOLVE / POLICY HELPERS
    // ════════════════════════════════════════════════════════════════════

    /**
     * Run the Resolve layer (Layer 2) for expense-type entries.
     * Returns [accountSource, confidence, autoApply, resolvedFund|null]
     */
    private function resolveAccountForEntry(User $user, array $parsed, Entry $entry): array
    {
        // Explicit account in message (user said "pakai GoPay")
        if (!empty($parsed['account_name'])) {
            $fund = $this->funds->findFundByName($user, $parsed['account_name']);
            return ['explicit', 1.0, false, $fund];
        }

        $merchant = $parsed['merchant'] ?? null;

        // Resolve from behavioral memory: merchant → account
        if ($merchant) {
            $memRow = $this->memory->resolve($user->id, 'merchant_account', $merchant);
            if ($memRow) {
                $fund = Fund::find($memRow->value['account_id'] ?? null);
                return [
                    'learned',
                    (float) $memRow->behavioral_confidence,
                    $memRow->canAutoApply(),
                    $fund,
                ];
            }
        }

        // Fallback: category → account (future)
        // Fallback: user default spending fund
        $defaultFund = $user->getDefaultSpendingFund();
        if ($defaultFund) {
            return ['default', 0.0, false, $defaultFund];
        }

        return ['none', 0.0, false, null];
    }

    /**
     * Send an account selection keyboard for needs_clarification mode.
     * Shows all active funds as buttons.
     */
    private function askAccountSelection(string $chatId, int $entryId, User $user): void
    {
        $funds = $this->funds->getFundsForUser($user)->where('fund_type', 'spending_budget');
        if ($funds->isEmpty()) {
            $this->telegram->sendMessage($chatId, 'Belum ada akun yang tersimpan. Set up dulu di /setup.');
            return;
        }

        $accounts = $funds->map(fn ($f) => ['id' => $f->id, 'name' => $f->name])->toArray();
        $this->telegram->sendAccountSelectionKeyboard($chatId, $entryId, $accounts);
    }

    /**
     * Build the confirmation text for soft_confirmation mode.
     * Suggests the learned account as a light question.
     */
    private function buildSoftConfirmationText(array $parsed, float $confidence, ?Fund $suggestedFund): string
    {
        $amount = number_format($parsed['amount'] ?? 0, 0, ',', '.');
        $item   = $parsed['note'] ?? $parsed['merchant'] ?? $parsed['food_item'] ?? 'ini';
        $account = $suggestedFund?->name ?? 'akun default';

        return "Tercatat {$item} Rp {$amount}.\nIni pakai {$account} kayak biasanya kan?";
    }

    /**
     * Confirm an entry immediately and send the confirmation + undo button.
     * Used for high-confidence (≥0.90) and auto_apply flows.
     */
    private function confirmAndSendWithUndo(User $user, string $chatId, Entry $entry): void
    {
        $this->entries->confirmEntry($entry);
        $this->streaks->updateAfterConfirmation($user, $entry->type);
        $this->applyFundEffect($user, $entry);
        $this->applyBillDebtEffect($user, $entry);

        $todayTotal = $this->getTodayTotalForType($user, $entry->type);
        $remaining  = $this->getRemainingForType($user, $entry->type);
        $parsedArr  = $this->entryToParsedArray($entry);
        
        $fundName = $entry->sourceFund ? $entry->sourceFund->name : 'Akun Utama';
        $parsedArr['deducted_from'] = $fundName;
        
        $text       = $this->telegram->formatConfirmedMessage($entry->type, $parsedArr, $todayTotal, $remaining);

        $undoToken  = \Illuminate\Support\Str::random(16);
        $entry->update([
            'undo_token'      => $undoToken,
            'undo_expires_at' => now()->addMinutes(5),
        ]);

        // Build dashboard edit URL so the user can edit from Telegram immediately
        $editUrl = URL::temporarySignedRoute(
            'dashboard.auth',
            now()->addMinutes(30),
            ['telegram_id' => $user->telegram_chat_id]
        );

        // For meal entries, include a [🔢 Edit Kalori] button
        $mealEntryId = in_array($entry->type, ['meal', 'log_meal_and_expense']) ? $entry->id : null;
        $msgId = $this->telegram->sendConfirmedWithUndoAndEdit($chatId, $text, $undoToken, $editUrl, $mealEntryId);
        if ($msgId) {
            $entry->update(['telegram_message_id' => $msgId]);
        }

        // Dispatch behavioral memory update asynchronously
        UpdateBehavioralMemory::dispatch($entry->id, $chatId)->onQueue('low');
    }

    // ════════════════════════════════════════════════════════════════════
    // v2.1 — CALLBACK HANDLERS
    // ════════════════════════════════════════════════════════════════════

    /**
     * Handle account selection: user tapped [GoPay] / [BCA] / [Cash].
     * Format: acct_sel:{entryId}:{fundId}
     */
    private function handleAccountSelectionCallback(User $user, string $chatId, string $callbackQueryId, string $data, int $messageId): void
    {
        $parts   = explode(':', $data);
        $entryId = (int) ($parts[1] ?? 0);
        $fundId  = (int) ($parts[2] ?? 0);

        $this->telegram->answerCallbackQuery($callbackQueryId);

        $entry = Entry::forUser($user->id)->pending()->find($entryId);
        $fund  = $fundId ? Fund::find($fundId) : null;

        if (!$entry || !$fund) {
            $this->telegram->editMessage($chatId, $messageId, 'Entry tidak ditemukan.');
            return;
        }

        $entry->update([
            'source_fund_id'        => $fund->id,
            'source_fund_confirmed' => true,
        ]);

        // Dispatch behavioral correction if user chose a different account than memory suggested
        if ($entry->merchant) {
            $memRow = $this->memory->resolve($user->id, 'merchant_account', $entry->merchant);
            $suggestedFundId = $memRow ? ($memRow->value['account_id'] ?? null) : null;
            if ($suggestedFundId && (int) $suggestedFundId !== $fund->id) {
                ProcessBehavioralCorrection::dispatch(
                    $entry->id,
                    'wrong_account',
                    'merchant_account',
                    $entry->merchant,               // old subject (same merchant)
                    $entry->merchant,               // new subject (same merchant, new value)
                    ['account_id' => $fund->id, 'account_name' => $fund->name]
                )->onQueue('low');
            }
        }

        // Confirm the entry now that we have an account
        $this->confirmAndSendWithUndo($user, $chatId, $entry);

        // Edit the account selection message to remove the buttons
        $this->telegram->editMessage($chatId, $messageId, "Pakai {$fund->name}.");
    }

    /**
     * Handle undo button tap.
     * Format: undo:{undoToken}
     * Edits the original confirmation message to show "Oke, dibatalin."
     */
    private function handleUndoCallback(User $user, string $chatId, string $callbackQueryId, string $data, int $messageId): void
    {
        $token = substr($data, 5); // strip "undo:"
        $entry = Entry::forUser($user->id)
            ->where('undo_token', $token)
            ->first();

        if (!$entry) {
            $this->telegram->answerCallbackQuery($callbackQueryId, 'Entry tidak ditemukan.');
            return;
        }

        if (!$entry->isUndoable()) {
            $this->telegram->answerCallbackQuery($callbackQueryId, 'Waktu undo sudah habis.');
            return;
        }

        // Reverse all fund transactions
        $transactions = \App\Models\FundTransaction::where('entry_id', $entry->id)->get();
        foreach ($transactions as $trx) {
            $fund = \App\Models\Fund::find($trx->fund_id);
            if ($fund) {
                if ($trx->transaction_type === 'deposit') {
                    $this->funds->debitFund($fund, $trx->amount, $entry->id, 'Undo entry');
                } elseif ($trx->transaction_type === 'withdrawal') {
                    $this->funds->creditFund($fund, $trx->amount, $entry->id, 'Undo entry');
                }
            }
        }

        $entry->markUndone();

        // Edit the original confirmation message — remove buttons, update text
        if ($entry->telegram_message_id) {
            $this->telegram->stripUndoButton($chatId, $entry->telegram_message_id, 'Oke, dibatalin.');
        }

        $this->telegram->answerCallbackQuery($callbackQueryId, 'Dibatalin.');
    }

    /**
     * Handle behavioral memory consent gate response.
     * Format: consent_yes:{domain}:{subject} | consent_no:{domain}:{subject}
     */
    private function handleConsentCallback(User $user, string $chatId, string $callbackQueryId, string $data, int $messageId): void
    {
        $isYes   = str_starts_with($data, 'consent_yes:');
        $payload = $isYes ? substr($data, 12) : substr($data, 11);
        $parts   = explode(':', $payload, 2);
        $domain  = $parts[0] ?? '';
        $subject = $parts[1] ?? '';

        $this->telegram->answerCallbackQuery($callbackQueryId);

        if ($isYes) {
            $this->memory->applyConsent($user->id, $domain, $subject);
            $this->telegram->editMessage($chatId, $messageId, 'Oke, aku otomatisin ke depannya.');
        } else {
            $this->memory->denyConsent($user->id, $domain, $subject);
            $this->telegram->editMessage($chatId, $messageId, 'Oke, tidak akan aku otomatisin.');
        }
    }

    // ════════════════════════════════════════════════════════════════════
    // RECEIPT PHOTO SCANNING
    // ════════════════════════════════════════════════════════════════════

    /**
     * Handle incoming photo message — attempt receipt OCR via vision AI.
     *
     * Message format: "__photo:{fileId}|caption:{optionalCaption}"
     */
    private function handleReceiptPhoto(User $user, string $chatId, string $message): void
    {
        // Parse file_id and optional caption from the sentinel message
        preg_match('/^__photo:([^\|]+)/', $message, $fileMatch);
        preg_match('/\|caption:(.*)$/', $message, $captionMatch);

        $fileId  = $fileMatch[1] ?? null;
        $caption = $captionMatch[1] ?? '';

        if (!$fileId) {
            $this->telegram->sendMessage($chatId, 'Foto tidak bisa diproses, coba lagi ya.');
            return;
        }

        // Acknowledge receipt
        $this->telegram->sendMessage($chatId, '🧾 Sedang memindai struk...');

        $scanner = app(\App\Services\ReceiptScanService::class);
        $receiptData = $scanner->extractFromTelegramPhoto($fileId, $caption);

        if (!$receiptData || ($receiptData['confidence'] ?? 1) < 0.4) {
            $this->telegram->sendMessage(
                $chatId,
                "Hmm, Butler tidak bisa baca struk ini. 😕\n\nCoba foto lebih dekat, atau ketik manual ya: _makan warteg 25k_"
            );
            return;
        }

        // Map to standard parsed format and create pending entry
        $parsed = $scanner->mapToEntryParsed($receiptData);
        $rawMessage = $caption ?: "Struk {$receiptData['merchant']}";
        $entry  = $this->entries->createPendingEntry($user, $parsed, $rawMessage);

        // Show confirmation with receipt details
        $amountF    = 'Rp ' . number_format($entry->amount ?? 0, 0, ',', '.');
        $merchantStr = $entry->merchant ? " di *{$entry->merchant}*" : '';
        $itemsStr   = '';
        if (!empty($receiptData['items'])) {
            $itemLines = collect($receiptData['items'])->take(4)->map(fn($i) =>
                "  • {$i['name']}: Rp " . number_format($i['price'] ?? 0, 0, ',', '.')
            )->join("\n");
            $itemsStr = "\n{$itemLines}";
        }

        $msg = "🧾 *Struk terdeteksi*{$merchantStr}\n\n💰 Total: *{$amountF}*{$itemsStr}\n\nKonfirmasi catat?";

        $this->telegram->sendMessageWithInlineKeyboard(
            $chatId,
            $msg,
            $this->telegram->buildConfirmationButtons($entry->id)
        );
    }
}
