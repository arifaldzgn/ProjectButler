<?php

namespace App\Services;

use App\Models\Reminder;
use App\Models\Streak;
use App\Models\User;
use Illuminate\Support\Facades\Log;

class OnboardingService
{
    private TelegramService $telegram;
    private FundService $funds;
    private BillService $bills;
    private DebtService $debts;
    private AIService $ai;

    public function __construct(
        TelegramService $telegram,
        FundService $funds,
        BillService $bills,
        DebtService $debts,
        AIService $ai
    ) {
        $this->telegram = $telegram;
        $this->funds = $funds;
        $this->bills = $bills;
        $this->debts = $debts;
        $this->ai = $ai;
    }

    /**
     * Process a message during onboarding.
     * Returns true if handled by onboarding, false if main router should take over.
     */
    public function handle(User $user, string $chatId, string $message): bool
    {
        if ($user->isOnboardingComplete()) {
            return false;
        }

        $step = $user->onboarding_step;

        // Fund detail steps (dynamic: fund_detail_emergency, fund_detail_bills, etc.)
        if (str_starts_with($step, 'fund_detail_')) {
            return $this->handleFundDetail($user, $chatId, $message, $step);
        }

        return match ($step) {
            'mode_select', 'new', null => $this->handleModeSelect($user, $chatId),
            'asked_name' => $this->handleName($user, $chatId, $message),
            'asked_income' => $this->handleIncome($user, $chatId, $message),
            'asked_spending_budget' => $this->handleSpendingBudget($user, $chatId, $message),
            'asked_funds' => $this->handleFundSelection($user, $chatId, $message),
            'asked_calorie_goal' => $this->handleCalorieGoal($user, $chatId, $message),
            default => false,
        };
    }

    // ════════════════════════════════════════════════════════════════════
    // PHASE 0 — MODE SELECTION
    // ════════════════════════════════════════════════════════════════════

    private function handleModeSelect(User $user, string $chatId): bool
    {
        $text = "Halo! Aku Butler, asisten harian kamu 👋\n\n"
            . "Aku bisa bantu dua hal utama:\n\n"
            . "💰 *Finance Tracker*\n"
            . "Catat pengeluaran, tabungan, tagihan, dan pantau kesehatan keuangan kamu\n\n"
            . "🥗 *Calorie Tracker*\n"
            . "Catat makan, pantau kalori harian, dan bantu kamu konsisten dengan diet\n\n"
            . "Mau mulai dari mana?";

        $buttons = [
            ['text' => '💰 Finance', 'callback_data' => 'onboard:mode:finance'],
            ['text' => '🥗 Kalori', 'callback_data' => 'onboard:mode:calorie'],
            ['text' => '✨ Dua-duanya', 'callback_data' => 'onboard:mode:both'],
        ];

        $this->telegram->sendMessageWithInlineKeyboard($chatId, $text, $buttons);
        $user->update(['onboarding_step' => 'mode_select']);
        return true;
    }

    /**
     * Handle mode selection callback (called from MessageRouter on callback_query).
     */
    public function handleModeCallback(User $user, string $chatId, string $mode): void
    {
        $user->update([
            'tracking_mode' => $mode,
            'onboarding_step' => 'asked_name',
        ]);

        $modeLabel = match ($mode) {
            'finance' => 'Finance Tracker',
            'calorie' => 'Calorie Tracker',
            default => 'Finance + Calorie Tracker',
        };

        $text = "Mantap, *{$modeLabel}* dipilih!\n\nNama kamu siapa?";
        $this->telegram->sendMessage($chatId, $text);
    }

    // ════════════════════════════════════════════════════════════════════
    // PHASE 1 — FINANCE SETUP
    // ════════════════════════════════════════════════════════════════════

    private function handleName(User $user, string $chatId, string $rawName): bool
    {
        $name = trim($rawName);
        if (empty($name) || strlen($name) > 128) {
            $this->telegram->sendMessage($chatId, "Hmm, coba ketik nama kamu ya (singkat aja).");
            return true;
        }

        $user->update(['name' => $name]);

        // Branch based on tracking mode
        if ($user->isFinanceMode()) {
            $user->update(['onboarding_step' => 'asked_income']);
            $text = "Hai {$name}! 👋\n\n"
                . "Berapa pemasukan bulanan kamu? (gaji atau total income)\n"
                . "Ini buat Butler bisa kasih konteks yang lebih relevan.\n"
                . "(Ketik nominalnya, atau \"skip\")";
        } else {
            // Calorie-only mode — go straight to calorie goal
            $user->update(['onboarding_step' => 'asked_calorie_goal']);
            $text = "Hai {$name}! 🥗\n\nBerapa target kalori harian kamu?\n(Ketik angkanya, atau \"skip\")";
        }

        $this->telegram->sendMessage($chatId, $text);
        return true;
    }

    private function handleIncome(User $user, string $chatId, string $message): bool
    {
        $income = null;
        $savings = null;
        $savedMsgParts = [];

        if (!$this->isSkip($message)) {
            $parsed = $this->ai->parseOnboardingCombo($message);
            $income = $parsed['monthly_income'] ?? null;
            $savings = $parsed['initial_savings'] ?? null;

            if ($income === null && $savings === null) {
                $income = $this->parseAmount($message);
            }

            if ($income === null && $savings === null) {
                $this->telegram->sendMessage($chatId,
                    "Hmm, Butler nggak nangkep nominalnya. Coba ketik angkanya (contoh: 5jt atau gaji 5jt, tabungan 10jt), atau \"skip\"."
                );
                return true;
            }

            if ($income !== null) {
                $incomeText = 'Rp ' . number_format($income, 0, ',', '.');
                $user->update(['has_income_set' => true]);
                $savedMsgParts[] = "income {$incomeText}/bulan";
            }

            if ($savings !== null) {
                $this->funds->createFund($user, [
                    'fund_type' => 'savings',
                    'name' => 'Tabungan',
                    'initial_balance' => $savings,
                ]);
                $savingsText = 'Rp ' . number_format($savings, 0, ',', '.');
                $savedMsgParts[] = "tabungan awal {$savingsText}";
                
                // Add savings to context so they don't get asked again unnecessarily
                $context = $user->onboarding_context ?? [];
                $selected = $context['selected_funds'] ?? [];
                if (!in_array('savings', $selected)) {
                    $selected[] = 'savings';
                    $context['selected_funds'] = $selected;
                    $user->update(['onboarding_context' => $context]);
                }
            }
        }

        $user->update([
            'monthly_income_idr' => $income,
            'onboarding_step' => 'asked_spending_budget',
        ]);

        if (empty($savedMsgParts)) {
            $savedMsg = "Oke, dilewati.";
        } else {
            $savedMsg = "Oke, " . implode(' & ', $savedMsgParts) . " tersimpan 👍";
        }

        $text = "{$savedMsg}\n\nBerapa budget belanja harian kamu?\n(Contoh: 150000 atau 150rb, atau \"skip\")";
        $this->telegram->sendMessage($chatId, $text);
        return true;
    }

    private function handleSpendingBudget(User $user, string $chatId, string $message): bool
    {
        $budget = null;

        if (!$this->isSkip($message)) {
            $budget = $this->parseAmount($message);
            if ($budget === null || $budget <= 0) {
                $this->telegram->sendMessage($chatId,
                    "Butler nggak nangkep nominalnya. Coba ketik angka (contoh: 150000 atau 150rb), atau \"skip\"."
                );
                return true;
            }
        }

        $user->update([
            'daily_budget_idr' => $budget,
            'onboarding_step'  => 'asked_funds',
            'onboarding_context' => ['selected_funds' => []],
        ]);

        $this->sendFundSelectionPrompt($user, $chatId);
        return true;
    }

    /**
     * Handle fund type toggles during onboarding (via callback).
     * Stores selected funds in onboarding_context and proceeds.
     */
    public function handleFundSelectionCallback(User $user, string $chatId, string $fundType, ?int $messageId = null): void
    {
        if ($fundType === 'skip') {
            $this->completeFundSetup($user, $chatId, []);
            return;
        }

        if ($fundType === 'confirm') {
            $context = $user->onboarding_context ?? [];
            $selectedFunds = $context['selected_funds'] ?? [];
            if (empty($selectedFunds)) {
                $this->completeFundSetup($user, $chatId, []);
            } else {
                $context['fund_queue'] = $selectedFunds;
                $user->update(['onboarding_context' => $context]);
                $this->processNextFundInQueue($user, $chatId);
            }
            return;
        }

        // ── Explain button: send description, keep the selection active ──
        if ($fundType === 'explain') {
            $explanation = "📚 *Penjelasan Jenis Dana*\n\n"
                . "🛡️ *Dana Darurat*\n"
                . "Uang simpanan untuk keadaan darurat (sakit, PHK, dll).\n"
                . "Idealnya 3-6x pengeluaran bulanan kamu.\n\n"
                . "🏠 *Tagihan Tetap (Bills)*\n"
                . "Pengeluaran rutin yang jumlahnya tetap tiap bulan.\n"
                . "Contoh: kos, listrik, internet, Spotify, BPJS, gym.\n\n"
                . "✈️ *Sinking Fund*\n"
                . "Menabung untuk tujuan spesifik dengan deadline.\n"
                . "Contoh: liburan, beli laptop, nikah, renovasi.\n\n"
                . "📈 *Tabungan / Invest*\n"
                . "Tabungan jangka panjang atau investasi rutin.\n"
                . "Contoh: deposito, reksadana, emas.\n\n"
                . "💳 *Cicilan / Utang*\n"
                . "Cicilan aktif yang lagi berjalan.\n"
                . "Contoh: kredit motor, KPR, paylater, kartu kredit.\n\n"
                . "_Sekarang pilih yang kamu punya di atas ⬆️_";
            $this->telegram->sendMessage($chatId, $explanation);
            return; // Don't advance onboarding — stay on asked_funds
        }

        // Toggle selection
        $context = $user->onboarding_context ?? [];
        $selectedFunds = $context['selected_funds'] ?? [];

        if (in_array($fundType, $selectedFunds)) {
            $selectedFunds = array_values(array_diff($selectedFunds, [$fundType]));
        } else {
            $selectedFunds[] = $fundType;
        }

        $context['selected_funds'] = $selectedFunds;
        $user->update(['onboarding_context' => $context]);

        if ($messageId) {
            $this->updateFundSelectionMessage($user, $chatId, $messageId);
        } else {
            $this->sendFundSelectionPrompt($user, $chatId);
        }
    }

    private function updateFundSelectionMessage(User $user, string $chatId, int $messageId): void
    {
        $context = $user->onboarding_context ?? [];
        $selected = $context['selected_funds'] ?? [];

        $text = "📊 *Kamu punya dana-dana ini nggak?*\n\n"
            . "Pilih yang udah kamu punya (boleh lebih dari satu).\n"
            . "Kalau bingung, tap tombol *ℹ️ Penjelasan* dulu.";

        $buttons = $this->buildFundSelectionButtons($selected);

        $this->telegram->editMessageWithKeyboard($chatId, $messageId, $text, $buttons);
    }

    private function sendFundSelectionPrompt(User $user, string $chatId): void
    {
        $context = $user->onboarding_context ?? [];
        $selected = $context['selected_funds'] ?? [];

        $text = "📊 *Kamu punya dana-dana ini nggak?*\n\n"
            . "Pilih yang udah kamu punya (boleh lebih dari satu).\n"
            . "Kalau bingung, tap tombol *ℹ️ Penjelasan* dulu.";

        $buttons = $this->buildFundSelectionButtons($selected);

        $this->telegram->sendFundSelectionKeyboard($chatId, $text, $buttons);
    }

    private function buildFundSelectionButtons(array $selected): array
    {
        $opts = [
            'emergency' => ['label' => '🛡️ Dana Darurat', 'emoji' => '🛡️'],
            'bills'     => ['label' => '🏠 Tagihan Tetap', 'emoji' => '🏠'],
            'sinking'   => ['label' => '✈️ Sinking Fund', 'emoji' => '✈️'],
            'savings'   => ['label' => '📈 Tabungan/Invest', 'emoji' => '📈'],
            'debt'      => ['label' => '💳 Cicilan/Utang', 'emoji' => '💳'],
        ];

        $buttons = [];
        foreach ($opts as $type => $opt) {
            $isSel = in_array($type, $selected);
            $prefix = $isSel ? '✅ ' : '';
            $buttons[] = [[
                'text' => "{$prefix}{$opt['label']}",
                'callback_data' => "onboard:fund:{$type}"
            ]];
        }

        $buttons[] = [['text' => 'ℹ️ Penjelasan Dana', 'callback_data' => 'onboard:fund:explain']];

        if (!empty($selected)) {
            $buttons[] = [['text' => '🚀 Lanjut Setup', 'callback_data' => 'onboard:fund:confirm']];
        } else {
            $buttons[] = [['text' => '⏭️ Nggak ada, skip', 'callback_data' => 'onboard:fund:skip']];
        }

        return $buttons;
    }

    private function processNextFundInQueue(User $user, string $chatId): void
    {
        $context = $user->onboarding_context ?? [];
        $queue = $context['fund_queue'] ?? [];

        if (empty($queue)) {
            $this->completeFundSetup($user, $chatId, $context['selected_funds'] ?? []);
            return;
        }

        $nextFund = array_shift($queue);
        $context['fund_queue'] = $queue;
        $context['current_fund'] = $nextFund;
        $user->update([
            'onboarding_context' => $context,
            'onboarding_step' => "fund_detail_{$nextFund}",
        ]);

        $question = $this->getFundDetailQuestion($nextFund);
        $this->telegram->sendMessage($chatId, $question);
    }

    private function getFundDetailQuestion(string $fundType): string
    {
        return match ($fundType) {
            'emergency' => "Berapa saldo dana darurat kamu sekarang?\n(skip kalau belum ada / belum tau)",
            'bills' => "Tagihan tetap bulanan kamu apa aja?\nContoh: \"kos 1.5jt tiap tanggal 1, internet 250rb tiap tanggal 5\"\n(Bisa ditambah nanti, skip dulu juga oke)",
            'sinking' => "Kamu lagi nabung untuk apa?\nContoh: \"liburan target 3jt bulan Desember\"\n(Bisa ditambah nanti)",
            'savings' => "Berapa total tabungan/investasi kamu sekarang?\n(skip kalau belum tau)",
            'debt' => "Ada cicilan aktif? Cicilan apa, berapa per bulan, jatuh tempo tanggal berapa?\nContoh: \"cicilan motor 800rb per bulan, tanggal 10\"\n(skip kalau nggak ada)",
            default => "Info tambahan untuk dana ini? (atau skip)",
        };
    }

    private function handleFundDetail(User $user, string $chatId, string $message, string $step): bool
    {
        $fundType = str_replace('fund_detail_', '', $step);
        $msg = strtolower(trim($message));

        if (!$this->isSkip($msg)) {
            $this->processFundDetailInput($user, $chatId, $fundType, $message);
        }

        // Move to next fund in queue
        $this->processNextFundInQueue($user, $chatId);
        return true;
    }

    private function processFundDetailInput(User $user, string $chatId, string $fundType, string $message): void
    {
        try {
            switch ($fundType) {
                case 'emergency':
                    $balance = $this->parseAmount($message);
                    if ($balance > 0) {
                        $this->funds->createFund($user, [
                            'fund_type' => 'emergency_fund',
                            'name' => 'Dana Darurat',
                            'initial_balance' => $balance,
                        ]);
                        $user->update(['has_emergency_fund' => true]);
                    }
                    break;

                case 'savings':
                    $balance = $this->parseAmount($message);
                    if ($balance > 0) {
                        $this->funds->createFund($user, [
                            'fund_type' => 'savings',
                            'name' => 'Tabungan',
                            'initial_balance' => $balance,
                        ]);
                    }
                    break;

                case 'debt':
                    $parsed = $this->parseDebtFromText($message);
                    if ($parsed) {
                        $this->debts->createDebt($user, $parsed);
                    }
                    break;

                case 'bills':
                    $parsedBills = $this->parseBillsFromText($message);
                    foreach ($parsedBills as $billData) {
                        $this->bills->createBill($user, $billData);
                    }
                    break;

                case 'sinking':
                    $parsed = $this->parseSinkingFundFromText($message);
                    if ($parsed) {
                        $this->funds->createFund($user, [
                            'fund_type' => 'sinking_fund',
                            'name' => $parsed['name'],
                            'target_amount' => $parsed['target_amount'],
                            'target_date' => $parsed['target_date'] ?? null,
                        ]);
                    }
                    break;
            }
        } catch (\Exception $e) {
            // Silent fail — user can add details later
        }
    }

    private function completeFundSetup(User $user, string $chatId, array $selectedFunds): void
    {
        // Determine next step based on mode
        if ($user->isCalorieMode()) {
            $user->update([
                'onboarding_context' => null,
                'onboarding_step' => 'asked_calorie_goal',
            ]);
            $text = "Setup keuangan selesai!\n\nSekarang, berapa target kalori harian kamu?\n(Ketik angkanya, atau \"skip\")";
        } else {
            $this->finalizeOnboarding($user, $chatId);
            return;
        }

        $this->telegram->sendMessage($chatId, $text);
    }

    private function handleFundSelection(User $user, string $chatId, string $message): bool
    {
        // This handles the case where user types instead of clicking button
        if ($this->isSkip($message)) {
            $this->completeFundSetup($user, $chatId, []);
            return true;
        }
        // Show prompt again
        $this->telegram->sendMessage($chatId, "Pilih pakai tombol di atas ya! Atau ketik \"skip\" buat lewatin.");
        return true;
    }

    private function handleCalorieGoal(User $user, string $chatId, string $message): bool
    {
        $goal = null;

        if (!$this->isSkip($message)) {
            $goal = (int) preg_replace('/[^0-9]/', '', $message);
            if ($goal <= 0 || $goal > 10000) {
                $this->telegram->sendMessage($chatId,
                    "Angka kalorinya kurang pas. Coba ketik angka (contoh: 2000), atau \"skip\"."
                );
                return true;
            }
        }

        $user->update(['daily_calorie_goal' => $goal]);
        $this->finalizeOnboarding($user, $chatId);
        return true;
    }

    private function finalizeOnboarding(User $user, string $chatId): void
    {
        // Auto-create default spending fund for finance users
        if ($user->isFinanceMode()) {
            $this->funds->createDefaultSpendingFund($user);
        }

        // Create initial streak record
        Streak::firstOrCreate(['user_id' => $user->id]);

        // Create default behavior-based reminders
        $this->createDefaultReminders($user);

        // Schedule setup-incomplete reminders
        $this->scheduleSetupIncompleteReminders($user);

        $user->update([
            'onboarding_step'         => 'complete',
            'onboarding_complete_at'  => now(),
            'onboarding_context'      => null,
        ]);

        // Fix 4: Clean welcome message — NO auto data dump/summary.
        // The user will see their data when they explicitly ask for it.
        $name = $user->name;
        $modes = [];
        if ($user->isFinanceMode()) $modes[] = 'keuangan 💰';
        if ($user->isCalorieMode()) $modes[] = 'kalori 🍽️';
        $modeStr = implode(' + ', $modes);

        $text = "🎉 *Setup selesai, {$name}!*\n\n"
            . "Butler siap pantau {$modeStr} kamu.\n\n"
            . "Langsung aja ketik transaksi pertama kamu!\n"
            . "_Contoh:_\n";

        if ($user->isFinanceMode()) {
            $text .= '`makan siang 35k` — catat pengeluaran' . "\n";
            $text .= '`gajian 5jt` — catat income' . "\n";
        }
        if ($user->isCalorieMode()) {
            $text .= '`makan nasi goreng` — catat kalori' . "\n";
        }
        if ($user->isFinanceMode()) {
            $text .= "\nAtau ketik `tampilkan semua uang saya` untuk lihat ringkasan dana.";
        }

        $this->telegram->sendMessage($chatId, $text);
    }

    private function buildSetupSummary(User $user): string
    {
        $lines = [];

        if ($user->monthly_income_idr) {
            $lines[] = '• Income: Rp ' . number_format($user->monthly_income_idr, 0, ',', '.') . '/bln';
        }
        if ($user->daily_budget_idr) {
            $lines[] = '• Budget harian: Rp ' . number_format($user->daily_budget_idr, 0, ',', '.');
        }
        if ($user->daily_calorie_goal) {
            $lines[] = "• Target kalori: {$user->daily_calorie_goal} kcal/hari";
        }

        $funds = $user->funds()->get();
        foreach ($funds as $fund) {
            if ($fund->current_balance > 0) {
                $balF = number_format($fund->current_balance, 0, ',', '.');
                $lines[] = "• {$fund->name}: Rp {$balF}";
            }
        }

        $bills = $user->bills()->get();
        foreach ($bills as $bill) {
            $lines[] = "• Tagihan: {$bill->name} Rp " . number_format($bill->amount, 0, ',', '.');
        }

        return empty($lines) ? 'Semua siap!' : implode("\n", $lines);
    }

    private function createDefaultReminders(User $user): void
    {
        try {
            Reminder::create([
                'user_id' => $user->id,
                'type' => 'behavior_based',
                'is_system' => true,
                'trigger_condition' => ['type' => 'no_expense_log', 'by_time' => '20:00'],
                'message_template' => "Hei {name}, belum ada catatan pengeluaran hari ini. Ada yang kelewat? Coba ketik sekarang sebelum lupa.",
            ]);

            Reminder::create([
                'user_id' => $user->id,
                'type' => 'behavior_based',
                'is_system' => true,
                'trigger_condition' => ['type' => 'inactive_days', 'threshold' => 2],
                'message_template' => "Butler kangen, {name}. Udah 2 hari nggak ada catatan. Gimana kabar keuangan kamu?",
            ]);
        } catch (\Exception $e) {
            // Silent fail
        }
    }

    private function scheduleSetupIncompleteReminders(User $user): void
    {
        if (!$user->isFinanceMode()) {
            return;
        }

        $onboardedAt = now();
        $reminders = [
            ['field' => 'income', 'days' => 3, 'hour' => '10:00',
             'msg' => "Hei {name}, kalau kamu masukin income bulanan, Butler bisa kasih insight yang lebih akurat. Berapa income kamu per bulan?"],
            ['field' => 'bills', 'days' => 2, 'hour' => '20:00',
             'msg' => "Ada tagihan tetap bulanan nggak? Kos, internet, langganan? Butler bisa ingatkan sebelum jatuh tempo."],
            ['field' => 'emergency_fund', 'days' => 5, 'hour' => '19:00',
             'msg' => "Dana darurat itu penting banget. Kamu punya nggak? Kalau ada, berapa saldonya sekarang? Biar Butler bisa bantu pantau."],
            ['field' => 'debt', 'days' => 4, 'hour' => '19:00',
             'msg' => "Ada cicilan aktif nggak, {name}? Motor, KPR, paylater? Butler bisa ingatkan sebelum jatuh tempo biar nggak kena denda."],
        ];

        foreach ($reminders as $r) {
            Reminder::create([
                'user_id' => $user->id,
                'type' => 'setup_incomplete',
                'is_system' => true,
                'trigger_condition' => ['type' => 'setup_incomplete', 'field' => $r['field'], 'days_after_onboarding' => $r['days']],
                'trigger_time' => $r['hour'],
                'message_template' => $r['msg'],
            ]);
        }
    }

    // ════════════════════════════════════════════════════════════════════
    // PARSE HELPERS
    // ════════════════════════════════════════════════════════════════════

    private function isSkip(string $msg): bool
    {
        return in_array(strtolower(trim($msg)), ['skip', 'lewat', 'nggak ada', 'tidak', 'nope', '-']);
    }

    private function parseAmount(string $input): ?int
    {
        $input = strtolower(trim($input));
        // Remove leading 'rp' and any spaces or dots
        $input = preg_replace('/^rp\.?\s*/i', '', $input);
        $input = str_replace([' ', '.'], '', $input);

        if (preg_match('/^(\d+(?:,\d+)?)(jt|juta)$/i', $input, $m)) {
            return (int) ((float) str_replace(',', '.', $m[1]) * 1_000_000);
        }
        if (preg_match('/^(\d+(?:,\d+)?)(rb|ribu|k)$/i', $input, $m)) {
            return (int) ((float) str_replace(',', '.', $m[1]) * 1_000);
        }
        if (preg_match('/^(\d+)$/', $input, $m)) {
            return (int) $m[1];
        }

        return null;
    }

    private function parseDebtFromText(string $text): ?array
    {
        // Basic: "cicilan motor 800rb per bulan, tanggal 10"
        preg_match('/(\d+)\s*(?:rb|ribu|k)/i', $text, $amountMatch);
        preg_match('/tanggal\s*(\d+)/i', $text, $dayMatch);

        $amount = isset($amountMatch[1]) ? (int) $amountMatch[1] * 1000 : null;
        $day = isset($dayMatch[1]) ? (int) $dayMatch[1] : 1;

        if (!$amount) return null;

        // Extract name (text before the amount)
        $name = preg_replace('/\s*\d+(?:rb|ribu|k|jt|juta).*$/i', '', $text);
        $name = trim($name) ?: 'Cicilan';

        return [
            'name' => $name,
            'debt_type' => 'installment',
            'monthly_installment' => $amount,
            'due_day' => $day,
        ];
    }

    private function parseBillsFromText(string $text): array
    {
        $bills = [];
        // Split on commas for multiple bills
        $parts = preg_split('/,\s*/', $text);

        foreach ($parts as $part) {
            preg_match('/(\d+(?:[.,]\d+)?)\s*(jt|juta|rb|ribu|k)/i', $part, $amountMatch);
            preg_match('/tanggal\s*(\d+)/i', $part, $dayMatch);

            if (empty($amountMatch)) continue;

            $multiplier = strtolower($amountMatch[2] ?? '');
            $num = (float) str_replace(',', '.', $amountMatch[1]);
            $amount = in_array($multiplier, ['jt', 'juta']) ? (int)($num * 1_000_000) : (int)($num * 1_000);

            $day = isset($dayMatch[1]) ? (int) $dayMatch[1] : 1;
            $name = trim(preg_replace('/\s*\d+.*$/i', '', $part)) ?: 'Tagihan';

            $bills[] = ['name' => $name, 'amount' => $amount, 'due_day' => $day];
        }

        return $bills;
    }

    private function parseSinkingFundFromText(string $text): ?array
    {
        preg_match('/target\s*(\d+(?:[.,]\d+)?)\s*(jt|juta|rb|ribu|k)/i', $text, $targetMatch);

        $targetAmount = null;
        if (!empty($targetMatch)) {
            $multiplier = strtolower($targetMatch[2]);
            $num = (float) str_replace(',', '.', $targetMatch[1]);
            $targetAmount = in_array($multiplier, ['jt', 'juta']) ? (int)($num * 1_000_000) : (int)($num * 1_000);
        }

        $name = preg_replace('/\s*target.*$/i', '', $text);
        $name = trim($name) ?: 'Sinking Fund';

        return ['name' => $name, 'target_amount' => $targetAmount];
    }
}
