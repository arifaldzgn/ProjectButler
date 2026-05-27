<?php

namespace App\Services;

use App\Models\DailySummary;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

class DailySummaryService
{
    private EntryService $entryService;
    private AIService $ai;
    private AiLogService $aiLog;
    private TelegramService $telegram;
    private FundService $funds;
    private BillService $bills;
    private DebtService $debts;

    public function __construct(
        EntryService $entryService,
        AIService $ai,
        AiLogService $aiLog,
        TelegramService $telegram,
        FundService $funds,
        BillService $bills,
        DebtService $debts
    ) {
        $this->entryService = $entryService;
        $this->ai = $ai;
        $this->aiLog = $aiLog;
        $this->telegram = $telegram;
        $this->funds = $funds;
        $this->bills = $bills;
        $this->debts = $debts;
    }

    public function sendAndStore(): void
    {
        $users = User::where('onboarding_step', 'complete')->get();

        foreach ($users as $user) {
            try {
                $this->processForUser($user);
            } catch (\Exception $e) {
                Log::error('Daily summary failed for user', [
                    'user_id' => $user->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }

    private function processForUser(User $user): void
    {
        $today = Carbon::now($user->timezone)->toDateString();

        // Prevent duplicate sends
        $existing = DailySummary::forUser($user->id)
            ->where('summary_date', $today)
            ->where('summary_type', 'daily')
            ->first();

        if ($existing && $existing->was_delivered) {
            return;
        }

        // Gather data
        $entries = $this->entryService->getTodayEntries($user);
        $totalSpent = $this->entryService->getTodaySpending($user);
        $totalCalories = $this->entryService->getTodayCalories($user);
        $totalSaved = $this->entryService->getTotalSavings($user);
        $totalIncome = $this->entryService->getTodayIncome($user);
        $monthIncome = $this->entryService->getMonthIncome($user);
        $monthSavings = $this->entryService->getMonthSavings($user);
        $totalBillsPaid = $this->entryService->getTodayBillsPaid($user);
        $budgetRemaining = $this->entryService->getBudgetRemaining($user);
        $streak = $user->streak;
        $entryCount = $entries->count();

        // Funds snapshot
        $fundsSnapshot = $user->isFinanceMode()
            ? $this->funds->buildFundsSnapshot($user)
            : [];

        // Upcoming bills & debts (next 7 days)
        $billsDue = $user->isFinanceMode()
            ? $this->bills->getBillsDueSoonForContext($user, 7)
            : [];
        $debtsDue = $user->isFinanceMode()
            ? $this->debts->getDebtsDueSoonForContext($user, 7)
            : [];

        // Estimate free balance end of day
        $freeBalanceEod = null;
        if ($user->monthly_income_idr) {
            $monthlyDebt = $this->debts->getTotalMonthlyInstallments($user);
            $freeBalanceEod = $user->monthly_income_idr - $totalSpent - $monthlyDebt;
        }

        // Calorie status
        $calorieStatus = null;
        if ($user->daily_calorie_goal && $totalCalories > 0) {
            $ratio = $totalCalories / $user->daily_calorie_goal;
            $calorieStatus = $ratio < 0.85 ? 'under' : ($ratio > 1.10 ? 'over' : 'on_track');
        }

        // Build AI context
        $context = $this->buildSummaryContext(
            $user, $entries, $totalSpent, $totalIncome, $monthIncome,
            $totalCalories, $totalSaved, $monthSavings,
            $budgetRemaining, $streak, $fundsSnapshot, $billsDue, $debtsDue
        );

        // Call AI Prompt B
        $startTime = microtime(true);
        $summaryText = $this->ai->generateSummary($context);
        $latencyMs = (int) ((microtime(true) - $startTime) * 1000);

        $this->aiLog->logSummaryCall($user, json_encode($context), $summaryText, $latencyMs, (bool) $summaryText);

        if (!$summaryText) {
            $summaryText = $entryCount === 0
                ? "Hei {$user->name}, belum ada catatan hari ini. Coba ketik pengeluaran terakhir sebelum tidur 😊"
                : $this->buildFallbackSummary($user, $totalSpent, $totalCalories, $budgetRemaining, $streak, $monthIncome, $monthSavings);
        }

        // Store
        $summary = DailySummary::updateOrCreate(
            ['user_id' => $user->id, 'summary_date' => $today, 'summary_type' => 'daily'],
            [
                'total_spent_idr' => $totalSpent,
                'total_income_idr' => $totalIncome,
                'total_bills_paid' => $totalBillsPaid,
                'total_saved_idr' => $totalSaved,
                'budget_remaining' => $budgetRemaining,
                'free_balance_eod' => $freeBalanceEod,
                'total_calories' => $totalCalories,
                'calorie_goal' => $user->daily_calorie_goal,
                'calorie_status' => $calorieStatus,
                'funds_snapshot' => $fundsSnapshot ?: null,
                'bills_due_this_week' => $billsDue ?: null,
                'debts_due_this_week' => $debtsDue ?: null,
                'entry_count' => $entryCount,
                'streak_at_time' => $streak->log_current ?? 0,
                'ai_generated_text' => $summaryText,
                'ai_prompt_version' => $this->ai->getSummaryPromptVersion(),
            ]
        );

        $delivered = $this->telegram->sendMessage(
            (string) $user->telegram_chat_id,
            "📊 *Ringkasan Harian*\n\n" . $summaryText
        );

        $summary->update([
            'was_delivered' => $delivered,
            'delivered_at' => $delivered ? now() : null,
            'delivery_error' => $delivered ? null : 'Telegram delivery failed',
        ]);

        if ($delivered) {
            $user->update(['last_summary_sent_at' => now()]);
        }
    }

    private function buildSummaryContext(
        User $user,
        \Illuminate\Database\Eloquent\Collection $entries,
        int $totalSpent,
        int $totalIncome,
        int $monthIncome,
        int $totalCalories,
        int $totalSaved,
        int $monthSavings,
        ?int $budgetRemaining,
        ?\App\Models\Streak $streak,
        array $fundsSnapshot,
        array $billsDue,
        array $debtsDue
    ): array {
        $today = Carbon::now($user->timezone);

        $formattedEntries = $entries->map(function ($entry) {
            $data = ['type' => $entry->type, 'entry_time' => Carbon::parse($entry->entry_time)->format('H:i')];
            if (in_array($entry->type, ['expense', 'bill_payment', 'debt_payment', 'sinking_fund_deposit', 'saving', 'income'])) {
                $data['amount'] = $entry->amount;
            }
            if ($entry->type === 'expense') {
                $data['category'] = $entry->category;
                $data['merchant'] = $entry->merchant;
            }
            if ($entry->type === 'meal') {
                $data['food_item'] = $entry->food_item;
                $data['calories'] = $entry->calories;
            }
            return $data;
        })->toArray();

        $calorieRemaining = ($user->daily_calorie_goal && $totalCalories > 0)
            ? $user->daily_calorie_goal - $totalCalories
            : null;

        return [
            'user' => [
                'name' => $user->name,
                'monthly_income_idr' => $user->monthly_income_idr,
                'daily_budget_idr' => $user->daily_budget_idr,
                'tracking_mode' => $user->tracking_mode,
                'daily_calorie_goal' => $user->daily_calorie_goal,
            ],
            'date' => $today->translatedFormat('l, d F Y'),
            'entries' => $formattedEntries,
            'totals' => [
                'spent_idr'            => $totalSpent,
                'income_idr'           => $totalIncome,
                'month_income_idr'     => $monthIncome > 0 ? $monthIncome : null,
                'month_savings_idr'    => $monthSavings > 0 ? $monthSavings : null,
                'budget_remaining_idr' => $budgetRemaining,
                'calories_consumed'    => $totalCalories > 0 ? $totalCalories : null,
                'calorie_remaining'    => $calorieRemaining,
            ],
            'funds' => $fundsSnapshot,
            'upcoming' => [
                'bills_due_3_days' => array_filter($billsDue, fn($b) => ($b['due_day'] - now()->day) <= 3),
                'debts_due_3_days' => array_filter($debtsDue, fn($d) => ($d['due_day'] - now()->day) <= 3),
            ],
            'streak' => [
                'log_current' => $streak->log_current ?? 0,
                'log_longest' => $streak->log_longest ?? 0,
            ],
            'setup_flags' => [
                'has_income_set' => $user->has_income_set,
                'has_bills_setup' => $user->has_bills_setup,
                'has_emergency_fund' => $user->has_emergency_fund,
                'has_debt_declared' => $user->has_debt_declared,
            ],
        ];
    }

    private function buildFallbackSummary(
        User $user,
        int $totalSpent,
        int $totalCalories,
        ?int $budgetRemaining,
        ?\App\Models\Streak $streak,
        int $monthIncome = 0,
        int $monthSavings = 0
    ): string {
        $spentF = number_format($totalSpent, 0, ',', '.');
        $msg = "Hari ini kamu spend Rp {$spentF}.";

        if ($budgetRemaining !== null) {
            $remainF = number_format(abs($budgetRemaining), 0, ',', '.');
            $msg .= $budgetRemaining >= 0 ? " Sisa budget Rp {$remainF}." : " Melebihi budget Rp {$remainF}!";
        }

        if ($totalCalories > 0) {
            $msg .= "\n🔥 Kalori: {$totalCalories} kcal";
            if ($user->daily_calorie_goal) {
                $msg .= " / {$user->daily_calorie_goal} kcal";
            }
        }

        if ($monthIncome > 0) {
            $monthIncomeF = number_format($monthIncome, 0, ',', '.');
            $msg .= "\n💰 Income bulan ini: Rp {$monthIncomeF}";
        }

        if ($monthSavings > 0) {
            $monthSavingsF = number_format($monthSavings, 0, ',', '.');
            $msg .= "\n💎 Tabungan bulan ini: Rp {$monthSavingsF}";
        }

        $currentStreak = $streak->log_current ?? 0;
        if ($currentStreak > 0) {
            $msg .= "\n\n🔥 Streak: {$currentStreak} hari berturut-turut!";
        }

        return $msg;
    }
}
