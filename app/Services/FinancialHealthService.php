<?php

namespace App\Services;

use App\Models\Entry;
use App\Models\FinancialHealthScore;
use App\Models\Fund;
use App\Models\User;
use Carbon\Carbon;

/**
 * Financial Health Score Service
 *
 * Computes a composite 0-100 score based on 6 weighted factors:
 *
 * | Factor                          | Weight |
 * |---------------------------------|--------|
 * | Savings rate (income - expense) | 25%    |
 * | Emergency fund coverage         | 20%    |
 * | Debt-to-income ratio            | 20%    |
 * | Budget adherence                | 15%    |
 * | Bill payment consistency        | 10%    |
 * | Logging streak                  | 10%    |
 */
class FinancialHealthService
{
    /**
     * Calculate, persist, and return the financial health score for a user.
     *
     * @param  User  $user
     * @param  bool  $persist  Whether to save to financial_health_scores table
     * @return array           ['score' => int, 'components' => [...], 'band' => [...]]
     */
    public function calculate(User $user, bool $persist = true): array
    {
        $now   = Carbon::now($user->timezone);
        $since = $now->copy()->subDays(30);

        // ── Factor 1: Savings rate (25 pts) ─────────────────────────────
        $monthIncome  = (int) Entry::forUser($user->id)
            ->income()->confirmed()
            ->where('entry_time', '>=', $since)->sum('amount');
        $monthExpense = (int) Entry::forUser($user->id)
            ->whereIn('type', ['expense', 'bill_payment'])
            ->confirmed()
            ->where('entry_time', '>=', $since)->sum('amount');
        $monthSavings = (int) Entry::forUser($user->id)
            ->savings()->confirmed()
            ->where('entry_time', '>=', $since)->sum('amount');

        $incomeBase = max($monthIncome, $user->monthly_income_idr ?? 0);
        $savingsRatePct = $incomeBase > 0
            ? min(100, (($incomeBase - $monthExpense + $monthSavings) / $incomeBase) * 100)
            : 0;
        $savingsScore = (int) min(25, $savingsRatePct / 100 * 25 * 2); // 50% rate → full 25 pts

        // ── Factor 2: Emergency fund coverage (20 pts) ──────────────────
        // Target: 3× monthly expenses
        $efFund = Fund::forUser($user->id)
            ->where('fund_type', 'emergency_fund')
            ->active()
            ->sum('current_balance');
        $monthlyExpenseBase = $monthExpense > 0 ? $monthExpense : ($incomeBase * 0.6);
        $efTarget = $monthlyExpenseBase * 3;
        $efCoverage = $efTarget > 0 ? min(1, $efFund / $efTarget) : 0;
        $efScore = (int) round($efCoverage * 20);

        // ── Factor 3: Debt-to-income ratio (20 pts) ─────────────────────
        // Lower debt-to-income is better. 0% = 20 pts, 50%+ = 0 pts
        $totalDebt = (int) \App\Models\Debt::forUser($user->id)
            ->where('is_active', true)
            ->sum('remaining_balance');
        $annualIncome = $incomeBase * 12;
        $dtiRatio = $annualIncome > 0 ? min(1, $totalDebt / $annualIncome) : ($totalDebt > 0 ? 1 : 0);
        $debtScore = (int) round((1 - $dtiRatio) * 20);

        // ── Factor 4: Budget adherence (15 pts) ─────────────────────────
        // Days under daily budget / total logged days in last 30d
        $dailyBudget = $user->daily_budget_idr ?? 0;
        $adherenceScore = 0;
        if ($dailyBudget > 0) {
            $loggedDays = 0;
            $underDays  = 0;
            for ($i = 0; $i < 30; $i++) {
                $day = $now->copy()->subDays($i)->toDateString();
                $daySpend = (int) Entry::forUser($user->id)
                    ->whereIn('type', ['expense', 'bill_payment'])
                    ->confirmed()->forDate($day)->sum('amount');
                if ($daySpend > 0) {
                    $loggedDays++;
                    if ($daySpend <= $dailyBudget) {
                        $underDays++;
                    }
                }
            }
            $adherenceRatio = $loggedDays > 0 ? $underDays / $loggedDays : 0;
            $adherenceScore = (int) round($adherenceRatio * 15);
        }

        // ── Factor 5: Bill payment consistency (10 pts) ─────────────────
        $totalBills  = \App\Models\Bill::forUser($user->id)->active()->count();
        $paidBills   = \App\Models\Bill::forUser($user->id)->active()->where('this_month_paid', true)->count();
        $billScore   = $totalBills > 0 ? (int) round(($paidBills / $totalBills) * 10) : 8; // default 8 if no bills

        // ── Factor 6: Logging streak (10 pts) ───────────────────────────
        $streak      = $user->streak;
        $currentStreak = $streak->log_current ?? 0;
        // 30-day streak = full 10 pts
        $streakScore = (int) min(10, round($currentStreak / 30 * 10));

        // ── Composite score ──────────────────────────────────────────────
        $score = $savingsScore + $efScore + $debtScore + $adherenceScore + $billScore + $streakScore;
        $score = max(0, min(100, $score));

        $components = [
            'savings_rate'   => ['score' => $savingsScore,   'max' => 25, 'pct' => round($savingsRatePct, 1)],
            'emergency_fund' => ['score' => $efScore,        'max' => 20, 'months_covered' => round($efFund / max($monthlyExpenseBase, 1), 1)],
            'debt_ratio'     => ['score' => $debtScore,      'max' => 20, 'dti_pct' => round($dtiRatio * 100, 1)],
            'budget_adherence'  => ['score' => $adherenceScore, 'max' => 15],
            'bill_consistency'  => ['score' => $billScore,   'max' => 10],
            'logging_streak'    => ['score' => $streakScore, 'max' => 10, 'streak_days' => $currentStreak],
        ];

        if ($persist) {
            FinancialHealthScore::create([
                'user_id'      => $user->id,
                'score'        => $score,
                'components'   => $components,
                'calculated_at'=> now(),
            ]);
        }

        $scoreModel = new FinancialHealthScore(['score' => $score, 'components' => $components]);

        return [
            'score'      => $score,
            'components' => $components,
            'band'       => $scoreModel->getBand(),
        ];
    }

    /**
     * Get the last calculated score for a user, or calculate a fresh one.
     */
    public function getOrCalculate(User $user): array
    {
        $latest = FinancialHealthScore::forUser($user->id)
            ->latest()
            ->first();

        // Re-calculate if older than 1 day
        if (!$latest || $latest->calculated_at->diffInHours(now()) > 24) {
            return $this->calculate($user);
        }

        $band = $latest->getBand();
        return [
            'score'      => $latest->score,
            'components' => $latest->components,
            'band'       => $band,
            'cached'     => true,
            'age_hours'  => (int) $latest->calculated_at->diffInHours(now()),
        ];
    }

    /**
     * Get historical scores for trend chart (last N scores).
     */
    public function getHistory(User $user, int $limit = 30): array
    {
        return FinancialHealthScore::forUser($user->id)
            ->latest()
            ->limit($limit)
            ->get(['score', 'calculated_at'])
            ->map(fn($s) => [
                'score' => $s->score,
                'date'  => $s->calculated_at->toDateString(),
            ])
            ->toArray();
    }
}
