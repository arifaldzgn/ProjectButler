<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Streak extends Model
{
    /**
     * Only updated_at is managed (no created_at column).
     */
    const CREATED_AT = null;

    protected $fillable = [
        'user_id',
        'log_current',
        'log_longest',
        'log_last_date',
        'expense_current',
        'expense_longest',
        'expense_last_date',
        'meal_current',
        'meal_longest',
        'meal_last_date',
        'budget_current',
        'budget_longest',
        'budget_last_date',
        'calorie_current',
        'calorie_longest',
        'calorie_last_date',
    ];

    protected function casts(): array
    {
        return [
            'log_last_date'     => 'date',
            'expense_last_date' => 'date',
            'meal_last_date'    => 'date',
            'budget_last_date'  => 'date',
            'calorie_last_date' => 'date',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Update streak after a confirmed entry.
     *
     * Logic from spec:
     * - if last_date == today → no change (already logged today)
     * - if last_date == yesterday → current_streak += 1
     * - if last_date < yesterday → current_streak = 1 (streak broken)
     * - if current_streak > longest_streak → longest_streak = current_streak
     * - last_date = today
     *
     * @param string $type 'log', 'expense', or 'meal'
     * @param string $timezone User's timezone
     */
    public function updateStreak(string $type, string $timezone): void
    {
        $today         = Carbon::now($timezone)->toDateString();
        $yesterday     = Carbon::now($timezone)->subDay()->toDateString();
        $twoDaysAgo    = Carbon::now($timezone)->subDays(2)->toDateString();

        $currentKey  = "{$type}_current";
        $longestKey  = "{$type}_longest";
        $lastDateKey = "{$type}_last_date";

        $lastDate = $this->{$lastDateKey} ? $this->{$lastDateKey}->toDateString() : null;

        if ($lastDate === $today) {
            // Already logged today — no change
            return;
        }

        if ($lastDate === $yesterday) {
            // Consecutive day — normal increment
            $this->{$currentKey} += 1;
        } elseif ($lastDate === $twoDaysAgo) {
            // Missed exactly one day — 1-day grace: continue the streak
            $this->{$currentKey} += 1;
        } else {
            // Missed 2+ days — streak resets
            $this->{$currentKey} = 1;
        }

        if ($this->{$currentKey} > $this->{$longestKey}) {
            $this->{$longestKey} = $this->{$currentKey};
        }

        $this->{$lastDateKey} = $today;
        $this->save();
    }
}
