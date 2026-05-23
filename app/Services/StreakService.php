<?php

namespace App\Services;

use App\Models\Streak;
use App\Models\User;

class StreakService
{
    /**
     * Update streaks after an entry is confirmed.
     *
     * Updates both the general log streak and the type-specific streak.
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
            'meal' => $streak->updateStreak('meal', $timezone),
            default => null, // savings doesn't have its own streak
        };
    }
}
