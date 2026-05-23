<?php

namespace App\Services;

use App\Models\Entry;
use App\Models\User;
use Carbon\Carbon;

class EntryService
{
    /**
     * Create a pending entry (not yet confirmed by user).
     */
    public function createPendingEntry(User $user, array $parsed, string $rawMessage): Entry
    {
        $now = Carbon::now($user->timezone);

        // Determine entry_time from parsed data or current time
        $entryTime = $now;
        if (!empty($parsed['entry_time'])) {
            try {
                $entryTime = Carbon::parse($parsed['entry_time'], $user->timezone);
                // If only time was given, use today's date
                if ($entryTime->year < 2000) {
                    $entryTime = $now->copy()->setTimeFromTimeString($parsed['entry_time']);
                }
            } catch (\Exception $e) {
                $entryTime = $now;
            }
        }

        $data = [
            'user_id' => $user->id,
            'type' => $this->mapIntentToType($parsed['intent']),
            'ai_raw_input' => $rawMessage,
            'ai_intent' => $parsed['intent'],
            'ai_confidence' => $parsed['confidence'] ?? 0.5,
            'ai_prompt_version' => 'parse_v1',
            'entry_time' => $entryTime,
            'confirmed_at' => null, // Pending until user confirms
        ];

        // Type-specific fields
        switch ($parsed['intent']) {
            case 'expense':
                $data['amount'] = $parsed['amount'] ?? 0;
                $data['category'] = $parsed['category'] ?? 'other';
                $data['merchant'] = $parsed['merchant'] ?? null;
                $data['note'] = $parsed['note'] ?? null;
                break;

            case 'meal':
                $data['food_item'] = $parsed['food_item'] ?? 'Unknown food';
                $data['calories'] = $parsed['calories'] ?? null;
                $data['is_calorie_estimated'] = $parsed['is_calorie_estimated'] ?? true;
                $data['note'] = $parsed['note'] ?? null;
                break;

            case 'saving':
                $data['amount'] = $parsed['amount'] ?? 0;
                $data['note'] = $parsed['note'] ?? null;
                break;
        }

        return Entry::create($data);
    }

    /**
     * Confirm a pending entry (user tapped ✅).
     */
    public function confirmEntry(Entry $entry): Entry
    {
        $entry->update(['confirmed_at' => now()]);
        return $entry->fresh();
    }

    /**
     * Cancel a pending entry (user tapped ❌).
     */
    public function cancelEntry(Entry $entry): void
    {
        $entry->delete(); // Soft delete
    }

    // ── Query Methods (all scoped to user_id) ──────────────────────────

    /**
     * Get today's total spending (confirmed only).
     */
    public function getTodaySpending(User $user): int
    {
        $today = Carbon::now($user->timezone)->toDateString();

        return (int) Entry::forUser($user->id)
            ->expenses()
            ->confirmed()
            ->forDate($today)
            ->sum('amount');
    }

    /**
     * Get today's total calories (confirmed only).
     */
    public function getTodayCalories(User $user): int
    {
        $today = Carbon::now($user->timezone)->toDateString();

        return (int) Entry::forUser($user->id)
            ->meals()
            ->confirmed()
            ->forDate($today)
            ->sum('calories');
    }

    /**
     * Get this month's total spending.
     */
    public function getMonthSpending(User $user): int
    {
        $now = Carbon::now($user->timezone);

        return (int) Entry::forUser($user->id)
            ->expenses()
            ->confirmed()
            ->forMonth($now->year, $now->month)
            ->sum('amount');
    }

    /**
     * Get budget remaining for today.
     */
    public function getBudgetRemaining(User $user): ?int
    {
        if (!$user->daily_budget_idr) {
            return null;
        }

        $todaySpent = $this->getTodaySpending($user);
        return $user->daily_budget_idr - $todaySpent;
    }

    /**
     * Get total savings (all time, confirmed only).
     */
    public function getTotalSavings(User $user): int
    {
        return (int) Entry::forUser($user->id)
            ->savings()
            ->confirmed()
            ->sum('amount');
    }

    /**
     * Get today's confirmed entries.
     */
    public function getTodayEntries(User $user): \Illuminate\Database\Eloquent\Collection
    {
        $today = Carbon::now($user->timezone)->toDateString();

        return Entry::forUser($user->id)
            ->confirmed()
            ->forDate($today)
            ->orderBy('entry_time')
            ->get();
    }

    /**
     * Get today's entry count.
     */
    public function getTodayEntryCount(User $user): int
    {
        $today = Carbon::now($user->timezone)->toDateString();

        return Entry::forUser($user->id)
            ->confirmed()
            ->forDate($today)
            ->count();
    }

    /**
     * Get total savings for this month.
     */
    public function getMonthSavings(User $user): int
    {
        $now = Carbon::now($user->timezone);

        return (int) Entry::forUser($user->id)
            ->savings()
            ->confirmed()
            ->forMonth($now->year, $now->month)
            ->sum('amount');
    }

    /**
     * Map AI intent to entry type.
     */
    private function mapIntentToType(string $intent): string
    {
        return match ($intent) {
            'expense' => 'expense',
            'meal' => 'meal',
            'saving' => 'saving',
            default => 'expense',
        };
    }
}
