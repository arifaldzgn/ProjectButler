<?php

namespace App\Services;

use App\Models\Fund;
use App\Models\FundTransaction;
use App\Models\User;
use Illuminate\Support\Facades\Log;

class FundService
{
    /**
     * Create a new fund for a user.
     */
    public function createFund(User $user, array $data): Fund
    {
        return Fund::create([
            'user_id' => $user->id,
            'fund_type' => $data['fund_type'],
            'name' => $data['name'],
            'description' => $data['description'] ?? null,
            'current_balance' => $data['initial_balance'] ?? 0,
            'initial_balance' => $data['initial_balance'] ?? 0,
            'target_amount' => $data['target_amount'] ?? null,
            'target_date' => $data['target_date'] ?? null,
            'monthly_contribution' => $data['monthly_contribution'] ?? null,
            'is_default_spending' => $data['is_default_spending'] ?? false,
        ]);
    }

    /**
     * Create the default "Uang Harian" spending budget fund on onboarding completion.
     */
    public function createDefaultSpendingFund(User $user): Fund
    {
        // Avoid duplicates
        $existing = Fund::forUser($user->id)
            ->where('is_default_spending', true)
            ->first();

        if ($existing) {
            return $existing;
        }

        return $this->createFund($user, [
            'fund_type' => 'spending_budget',
            'name' => 'Uang Harian',
            'is_default_spending' => true,
            'initial_balance' => 0,
        ]);
    }

    /**
     * Credit (add money) to a fund.
     */
    public function creditFund(Fund $fund, int $amount, ?int $entryId = null, ?string $note = null): FundTransaction
    {
        return $fund->credit($amount, $entryId, $note);
    }

    /**
     * Debit (remove money) from a fund.
     */
    public function debitFund(Fund $fund, int $amount, ?int $entryId = null, ?string $note = null): FundTransaction
    {
        return $fund->debit($amount, $entryId, $note);
    }

    /**
     * Get all active funds for a user.
     */
    public function getFundsForUser(User $user): \Illuminate\Database\Eloquent\Collection
    {
        return Fund::forUser($user->id)->active()->orderBy('fund_type')->get();
    }

    /**
     * Find a fund by name (fuzzy match) for a user.
     */
    public function findFundByName(User $user, string $name): ?Fund
    {
        // Exact match first
        $fund = Fund::forUser($user->id)
            ->active()
            ->where('name', $name)
            ->first();

        if ($fund) {
            return $fund;
        }

        // Partial match
        return Fund::forUser($user->id)
            ->active()
            ->where('name', 'like', '%' . $name . '%')
            ->first();
    }

    /**
     * Get total balance across all savings-type funds.
     */
    public function getTotalSavingsBalance(User $user): int
    {
        return (int) Fund::forUser($user->id)
            ->active()
            ->whereIn('fund_type', ['savings', 'emergency_fund', 'sinking_fund', 'financial_goal'])
            ->sum('current_balance');
    }

    /**
     * Build funds snapshot for daily summary.
     */
    public function buildFundsSnapshot(User $user): array
    {
        return Fund::forUser($user->id)
            ->active()
            ->get()
            ->map(fn($fund) => [
                'id' => $fund->id,
                'name' => $fund->name,
                'type' => $fund->fund_type,
                'balance' => $fund->current_balance,
                'target' => $fund->target_amount,
                'target_date' => $fund->target_date?->toDateString(),
                'on_track' => $fund->on_track,
            ])
            ->toArray();
    }
}
