<?php

namespace App\Services;

use App\Models\Entry;
use App\Models\User;
use Carbon\Carbon;

class EntryService
{
    /**
     * Create a pending entry (not yet confirmed by user).
     * Handles all v1.2 entry types.
     */
    public function createPendingEntry(User $user, array $parsed, string $rawMessage): Entry
    {
        $now = Carbon::now($user->timezone);
        $intent = $parsed['intent'];

        $entryTime = $now;
        if (!empty($parsed['entry_time'])) {
            try {
                $entryTime = $now->copy()->setTimeFromTimeString($parsed['entry_time']);
            } catch (\Exception $e) {
                $entryTime = $now;
            }
        }

        $metadata = [];
        if (!empty($parsed['fund_name'])) $metadata['fund_name'] = $parsed['fund_name'];
        if (!empty($parsed['source_fund'])) $metadata['source_fund'] = $parsed['source_fund'];
        if (!empty($parsed['target_fund'])) $metadata['target_fund'] = $parsed['target_fund'];

        $data = [
            'user_id' => $user->id,
            'type' => $this->mapIntentToType($intent),
            'ai_raw_input' => $rawMessage,
            'ai_intent' => $intent,
            'ai_confidence' => $parsed['confidence'] ?? 0.5,
            'ai_prompt_version' => 'parse_v1.3',
            'entry_time' => $entryTime,
            'metadata' => $metadata,
            'confirmed_at' => null,
        ];

        switch ($intent) {
            case 'log_expense':
                $data['amount'] = $parsed['amount'] ?? 0;
                $data['category'] = $parsed['category'] ?? 'other';
                $data['merchant'] = $parsed['merchant'] ?? null;
                $data['note'] = $parsed['note'] ?? null;
                break;

            case 'log_meal':
                $data['food_item'] = $parsed['food_item'] ?? 'Unknown food';
                $data['calories'] = $parsed['calories'] ?? null;
                $data['is_calorie_estimated'] = $parsed['is_calorie_estimated'] ?? true;
                $data['note'] = $parsed['note'] ?? null;
                break;

            case 'log_saving':
                $data['amount'] = $parsed['amount'] ?? 0;
                $data['note'] = $parsed['note'] ?? null;
                break;

            case 'log_income':
                $data['amount'] = $parsed['amount'] ?? 0;
                $data['note'] = $parsed['note'] ?? ($parsed['source'] ?? null);
                break;

            case 'log_bill_payment':
                $data['amount'] = $parsed['amount'] ?? 0;
                $data['merchant'] = $parsed['bill_name'] ?? null;
                $data['note'] = $parsed['bill_name'] ?? null;
                break;

            case 'log_debt_payment':
                $data['amount'] = $parsed['amount'] ?? 0;
                $data['note'] = $parsed['debt_name'] ?? null;
                break;

            case 'log_sinking_deposit':
                $data['amount'] = $parsed['amount'] ?? 0;
                $data['note'] = $parsed['fund_name'] ?? null;
                break;

            case 'transfer_fund':
                $data['amount'] = $parsed['amount'] ?? 0;
                $data['note'] = "Transfer ke " . ($parsed['target_fund'] ?? 'dana lain');
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
        $entry->delete();
    }

    // ── Query Methods ───────────────────────────────────────────────────

    public function getTodaySpending(User $user): int
    {
        $today = Carbon::now($user->timezone)->toDateString();
        return (int) Entry::forUser($user->id)
            ->whereIn('type', ['expense', 'bill_payment'])
            ->confirmed()->forDate($today)->sum('amount');
    }

    public function getTodayCalories(User $user): int
    {
        $today = Carbon::now($user->timezone)->toDateString();
        return (int) Entry::forUser($user->id)
            ->meals()->confirmed()->forDate($today)->sum('calories');
    }

    public function getTodayIncome(User $user): int
    {
        $today = Carbon::now($user->timezone)->toDateString();
        return (int) Entry::forUser($user->id)
            ->income()->confirmed()->forDate($today)->sum('amount');
    }

    public function getTodayBillsPaid(User $user): int
    {
        $today = Carbon::now($user->timezone)->toDateString();
        return (int) Entry::forUser($user->id)
            ->billPayments()->confirmed()->forDate($today)->sum('amount');
    }

    public function getMonthSpending(User $user): int
    {
        $now = Carbon::now($user->timezone);
        return (int) Entry::forUser($user->id)
            ->expenses()->confirmed()->forMonth($now->year, $now->month)->sum('amount');
    }

    public function getMonthIncome(User $user): int
    {
        $now = Carbon::now($user->timezone);
        return (int) Entry::forUser($user->id)
            ->income()->confirmed()->forMonth($now->year, $now->month)->sum('amount');
    }

    public function getBudgetRemaining(User $user): ?int
    {
        if (!$user->daily_budget_idr) {
            return null;
        }
        return $user->daily_budget_idr - $this->getTodaySpending($user);
    }

    public function getTotalSavings(User $user): int
    {
        return (int) Entry::forUser($user->id)
            ->savings()->confirmed()->sum('amount');
    }

    public function getTodayEntries(User $user): \Illuminate\Database\Eloquent\Collection
    {
        $today = Carbon::now($user->timezone)->toDateString();
        return Entry::forUser($user->id)->confirmed()->forDate($today)->orderBy('entry_time')->get();
    }

    public function getTodayEntryCount(User $user): int
    {
        $today = Carbon::now($user->timezone)->toDateString();
        return Entry::forUser($user->id)->confirmed()->forDate($today)->count();
    }

    private function mapIntentToType(string $intent): string
    {
        return match ($intent) {
            'log_expense' => 'expense',
            'log_meal' => 'meal',
            'log_saving' => 'saving',
            'log_income' => 'income',
            'log_bill_payment' => 'bill_payment',
            'log_debt_payment' => 'debt_payment',
            'log_sinking_deposit' => 'sinking_fund_deposit',
            'transfer_fund' => 'transfer',
            default => 'expense',
        };
    }
}
