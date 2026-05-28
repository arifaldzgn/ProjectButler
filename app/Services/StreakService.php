<?php

namespace App\Services;

use App\Models\Entry;
use App\Models\Streak;
use App\Models\User;

class StreakService
{
    /**
     * Update streaks after an entry is confirmed.
     *
     * Updates the general log streak, type-specific streak, and
     * domain-specific goal streaks (budget + calorie) when applicable.
     */
    public function updateAfterConfirmation(User $user, string $entryType): void
    {
        $streak = $user->streak;

        if (!$streak) {
            $streak = Streak::create(['user_id' => $user->id]);
        }

        $timezone = $user->timezone ?? 'Asia/Jakarta';

        // Always update general log streak
        $streak->updateStreak('log', $timezone);

        // Update type-specific streak
        match ($entryType) {
            'expense' => $streak->updateStreak('expense', $timezone),
            'meal'    => $streak->updateStreak('meal', $timezone),
            default   => null,
        };

        // Update domain goal streaks (checked after any entry type)
        $this->checkBudgetStreak($user, $streak, $timezone);

        if ($user->isCalorieMode() && $user->daily_calorie_goal) {
            $this->checkCalorieStreak($user, $streak, $timezone);
        }
    }

    /**
     * Check if the user stayed under daily budget today.
     * If yes, advance the budget streak; if no, reset it.
     */
    private function checkBudgetStreak(User $user, Streak $streak, string $timezone): void
    {
        if (!$user->daily_budget_idr || $user->daily_budget_idr <= 0) {
            return; // no budget set — skip
        }

        $todaySpend = Entry::where('user_id', $user->id)
            ->where('type', 'expense')
            ->whereNotNull('confirmed_at')
            ->where('is_undone', false)
            ->whereDate('entry_time', now()->timezone($timezone)->toDateString())
            ->sum('amount');

        $underBudget = $todaySpend <= $user->daily_budget_idr;

        if ($underBudget) {
            $streak->updateStreak('budget', $timezone);
        } else {
            // Over budget — mark streak as broken for today so it resets tomorrow
            $streak->budget_current   = 0;
            $streak->budget_last_date = null;
            $streak->save();
        }
    }

    /**
     * Check if the user hit their calorie goal today (within ±100 kcal tolerance).
     * "Hit" = calories consumed >= goal (for bulking) or within range (for cutting/maintenance).
     */
    private function checkCalorieStreak(User $user, Streak $streak, string $timezone): void
    {
        $goal = $user->daily_calorie_goal;
        if (!$goal) return;

        $todayCalories = Entry::where('user_id', $user->id)
            ->where('type', 'meal')
            ->whereNotNull('confirmed_at')
            ->where('is_undone', false)
            ->whereDate('entry_time', now()->timezone($timezone)->toDateString())
            ->sum('calories');

        $goalType = $user->calorie_goal_type ?? 'maintenance';

        $hit = match ($goalType) {
            'bulking'      => $todayCalories >= $goal,               // Must meet or exceed
            'cutting'      => $todayCalories > 0 && $todayCalories <= $goal,  // Must stay under
            'maintenance'  => abs($todayCalories - $goal) <= 150,    // Within 150 kcal either way
            default        => $todayCalories >= ($goal * 0.8),       // 80% of goal = "hit"
        };

        if ($hit) {
            $streak->updateStreak('calorie', $timezone);
        }
        // Don't reset calorie streak mid-day — user may log more meals later
    }
}
