<?php

namespace App\Services;

use App\Models\Debt;
use App\Models\User;

class DebtService
{
    /**
     * Create a new debt record for a user.
     */
    public function createDebt(User $user, array $data): Debt
    {
        $debt = Debt::create([
            'user_id' => $user->id,
            'name' => $data['name'],
            'debt_type' => $data['debt_type'] ?? 'installment',
            'total_amount' => $data['total_amount'] ?? $data['monthly_installment'] * 12,
            'monthly_installment' => $data['monthly_installment'],
            'interest_rate' => $data['interest_rate'] ?? null,
            'remaining_balance' => $data['remaining_balance'] ?? $data['total_amount'] ?? $data['monthly_installment'] * 12,
            'start_date' => $data['start_date'] ?? null,
            'due_day' => $data['due_day'],
            'end_date' => $data['end_date'] ?? null,
            'auto_remind' => $data['auto_remind'] ?? true,
            'remind_days_before' => $data['remind_days_before'] ?? 3,
        ]);

        // Mark user as having declared debt
        $user->update(['has_debt_declared' => true]);

        return $debt;
    }

    /**
     * Record a debt payment and reduce remaining balance.
     */
    public function recordPayment(Debt $debt, int $amount): void
    {
        $debt->recordPayment($amount);
    }

    /**
     * Get debts due within the next N days for a user.
     */
    public function getDebtsDueWithin(User $user, int $days = 3): \Illuminate\Database\Eloquent\Collection
    {
        $timezone = $user->timezone ?? 'Asia/Jakarta';

        return Debt::forUser($user->id)
            ->active()
            ->unpaidThisMonth()
            ->get()
            ->filter(fn($debt) => $debt->isDueWithin($days, $timezone));
    }

    /**
     * Try to find a matching debt by name for automatic payment matching.
     */
    public function findMatchingDebt(User $user, string $name): ?Debt
    {
        $debts = Debt::forUser($user->id)->active()->get();

        foreach ($debts as $debt) {
            if (stripos($name, $debt->name) !== false
                || stripos($debt->name, $name) !== false) {
                return $debt;
            }
        }

        return null;
    }

    /**
     * Reset this_month_paid for all debts (run on 1st of each month).
     */
    public function resetMonthlyPaymentStatus(): int
    {
        return Debt::where('this_month_paid', true)->update(['this_month_paid' => false]);
    }

    /**
     * Build upcoming debts array for AI summary context.
     */
    public function getDebtsDueSoonForContext(User $user, int $days = 7): array
    {
        return $this->getDebtsDueWithin($user, $days)
            ->map(fn($debt) => [
                'name' => $debt->name,
                'monthly_installment' => $debt->monthly_installment,
                'remaining_balance' => $debt->remaining_balance,
                'due_day' => $debt->due_day,
            ])
            ->values()
            ->toArray();
    }

    /**
     * Get total monthly debt burden for a user.
     */
    public function getTotalMonthlyInstallments(User $user): int
    {
        return (int) Debt::forUser($user->id)->active()->sum('monthly_installment');
    }
}
