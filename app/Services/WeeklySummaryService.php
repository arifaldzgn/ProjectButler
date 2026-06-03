<?php

namespace App\Services;

use App\Models\DailySummary;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

class WeeklySummaryService
{
    private EntryService $entryService;
    private AIService $ai;
    private AiLogService $aiLog;
    private TelegramService $telegram;
    private FundService $funds;

    public function __construct(
        EntryService $entryService,
        AIService $ai,
        AiLogService $aiLog,
        TelegramService $telegram,
        FundService $funds
    ) {
        $this->entryService = $entryService;
        $this->ai = $ai;
        $this->aiLog = $aiLog;
        $this->telegram = $telegram;
        $this->funds = $funds;
    }

    /**
     * Send weekly summaries to all eligible users.
     * Called every Sunday evening from the scheduler.
     */
    public function sendWeeklySummaries(): void
    {
        $users = User::where('onboarding_step', 'complete')
            ->where('daily_summary_enabled', true)   // re-use the daily summary opt-in flag
            ->get();

        foreach ($users as $user) {
            try {
                $this->processForUser($user);
            } catch (\Exception $e) {
                Log::error('Weekly summary failed', [
                    'user_id' => $user->id,
                    'error'   => $e->getMessage(),
                ]);
            }
        }
    }

    private function processForUser(User $user): void
    {
        $now     = Carbon::now($user->timezone);
        $weekEnd = $now->copy()->startOfDay();
        $weekStart = $weekEnd->copy()->subDays(6)->startOfDay();

        // Prevent duplicate sends within the same week
        $weekLabel = $weekStart->toDateString();
        $existing = DailySummary::forUser($user->id)
            ->where('summary_date', $weekLabel)
            ->where('summary_type', 'weekly')
            ->first();

        if ($existing && $existing->was_delivered) {
            return;
        }

        // ── Aggregate 7-day data ─────────────────────────────────────────
        $entries = \App\Models\Entry::forUser($user->id)
            ->confirmed()
            ->whereBetween('entry_time', [$weekStart, $weekEnd->copy()->endOfDay()])
            ->orderBy('entry_time')
            ->get();

        $totalSpent   = $entries->whereIn('type', ['expense', 'bill_payment'])->sum('amount');
        $totalIncome  = $entries->where('type', 'income')->sum('amount');
        $totalSaved   = $entries->where('type', 'saving')->sum('amount');
        $totalCalories= $entries->where('type', 'meal')->sum('calories');
        $entryCount   = $entries->count();

        // Daily breakdown
        $dailySpending = [];
        $dailyCalories = [];
        for ($i = 6; $i >= 0; $i--) {
            $day = $now->copy()->subDays($i)->toDateString();
            $dailySpending[$day] = (int) $entries
                ->whereIn('type', ['expense', 'bill_payment'])
                ->filter(fn($e) => Carbon::parse($e->entry_time)->toDateString() === $day)
                ->sum('amount');
            $dailyCalories[$day] = (int) $entries
                ->where('type', 'meal')
                ->filter(fn($e) => Carbon::parse($e->entry_time)->toDateString() === $day)
                ->sum('calories');
        }

        // Week-over-week comparison: previous 7 days
        $prevEnd   = $weekStart->copy()->subSecond();
        $prevStart = $prevEnd->copy()->subDays(6)->startOfDay();
        $prevEntries = \App\Models\Entry::forUser($user->id)
            ->confirmed()
            ->whereBetween('entry_time', [$prevStart, $prevEnd])
            ->get();
        $prevSpent   = (int) $prevEntries->whereIn('type', ['expense', 'bill_payment'])->sum('amount');
        $prevIncome  = (int) $prevEntries->where('type', 'income')->sum('amount');
        $prevCalories= (int) $prevEntries->where('type', 'meal')->sum('calories');

        $spendingDelta = $prevSpent > 0 ? round((($totalSpent - $prevSpent) / $prevSpent) * 100) : null;

        // Category breakdown
        $categoryTotals = $entries->where('type', 'expense')
            ->groupBy('category')
            ->map(fn($group) => $group->sum('amount'))
            ->sortByDesc(fn($v) => $v)
            ->take(5)
            ->toArray();

        // Funds snapshot
        $fundsSnapshot = $user->isFinanceMode()
            ? $this->funds->buildFundsSnapshot($user)
            : [];

        // Streak
        $streak = $user->streak;
        $weeklyBudget = $user->daily_budget_idr ? $user->daily_budget_idr * 7 : null;
        $net = $totalIncome - $totalSpent;

        // Build context for AI
        $context = [
            'period' => [
                'from'  => $weekStart->translatedFormat('d F'),
                'to'    => $weekEnd->copy()->subSecond()->translatedFormat('d F Y'),
            ],
            'user' => [
                'name'               => $user->name,
                'monthly_income_idr' => $user->monthly_income_idr,
                'daily_budget_idr'   => $user->daily_budget_idr,
                'weekly_budget_idr'  => $weeklyBudget,
                'tracking_mode'      => $user->tracking_mode,
                'daily_calorie_goal' => $user->daily_calorie_goal,
                'calorie_goal_type'  => $user->calorie_goal_type ?? 'maintenance',
            ],
            'totals' => [
                'spent_idr'     => $totalSpent,
                'income_idr'    => $totalIncome > 0 ? $totalIncome : null,
                'saved_idr'     => $totalSaved > 0 ? $totalSaved : null,
                'net_idr'       => $net,
                'calories'      => $totalCalories > 0 ? $totalCalories : null,
                'entry_count'   => $entryCount,
            ],
            'daily_spending' => $dailySpending,
            'category_breakdown' => $categoryTotals,
            'vs_prev_week' => [
                'spending_change_pct' => $spendingDelta,
                'prev_spent_idr'      => $prevSpent > 0 ? $prevSpent : null,
                'prev_income_idr'     => $prevIncome > 0 ? $prevIncome : null,
                'prev_calories'       => $prevCalories > 0 ? $prevCalories : null,
            ],
            'streak' => [
                'log_current' => $streak->log_current ?? 0,
                'log_longest' => $streak->log_longest ?? 0,
            ],
            'funds' => $fundsSnapshot,
        ];

        // Call AI
        $startTime = microtime(true);
        $summaryText = $this->ai->generateWeeklySummary($context);
        $latencyMs = (int) ((microtime(true) - $startTime) * 1000);

        $tokenUsage = $this->ai->getLastTokenUsage();
        $this->aiLog->logSummaryCall($user, json_encode($context), $summaryText ?? '', $latencyMs, (bool) $summaryText, null, $tokenUsage);

        if (!$summaryText) {
            $summaryText = $this->buildFallbackWeeklySummary($user, $totalSpent, $totalIncome, $totalSaved, $spendingDelta, $weeklyBudget);
        }

        // Store
        $summary = DailySummary::updateOrCreate(
            ['user_id' => $user->id, 'summary_date' => $weekLabel, 'summary_type' => 'weekly'],
            [
                'total_spent_idr'   => $totalSpent,
                'total_income_idr'  => $totalIncome,
                'total_saved_idr'   => $totalSaved,
                'total_calories'    => $totalCalories,
                'entry_count'       => $entryCount,
                'ai_generated_text' => $summaryText,
                'ai_prompt_version' => $this->ai->getWeeklySummaryPromptVersion(),
            ]
        );

        $delivered = $this->telegram->sendMessage(
            (string) $user->telegram_chat_id,
            "📅 *Ringkasan Mingguan*\n\n" . $summaryText
        );

        $summary->update([
            'was_delivered'    => $delivered,
            'delivered_at'     => $delivered ? now() : null,
            'delivery_error'   => $delivered ? null : 'Telegram delivery failed',
        ]);
    }

    private function buildFallbackWeeklySummary(
        User $user,
        int $totalSpent,
        int $totalIncome,
        int $totalSaved,
        ?int $spendingDelta,
        ?int $weeklyBudget
    ): string {
        $spentF = 'Rp ' . number_format($totalSpent, 0, ',', '.');
        $msg = "Minggu ini kamu menghabiskan {$spentF}.";

        if ($weeklyBudget) {
            $diff = $weeklyBudget - $totalSpent;
            $diffF = 'Rp ' . number_format(abs($diff), 0, ',', '.');
            $msg .= $diff >= 0 ? " Sisa budget {$diffF}. 💚" : " Melebihi budget {$diffF}. ⚠️";
        }

        if ($totalIncome > 0) {
            $incF = 'Rp ' . number_format($totalIncome, 0, ',', '.');
            $msg .= "\n💰 Pemasukan: {$incF}";
        }

        if ($totalSaved > 0) {
            $savF = 'Rp ' . number_format($totalSaved, 0, ',', '.');
            $msg .= "\n💎 Ditabung: {$savF}";
        }

        if ($spendingDelta !== null) {
            $sign = $spendingDelta >= 0 ? '+' : '';
            $msg .= "\n📊 vs minggu lalu: {$sign}{$spendingDelta}%";
        }

        return $msg;
    }
}
