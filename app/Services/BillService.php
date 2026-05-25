<?php

namespace App\Services;

use App\Models\Bill;
use App\Models\User;
use Carbon\Carbon;

class BillService
{
    /**
     * Create a new bill for a user.
     */
    public function createBill(User $user, array $data): Bill
    {
        $bill = Bill::create([
            'user_id' => $user->id,
            'name' => $data['name'],
            'amount' => $data['amount'],
            'category' => $data['category'] ?? 'bills',
            'due_day' => $data['due_day'],
            'due_day_flexibility' => $data['due_day_flexibility'] ?? 0,
            'source_fund_id' => $data['source_fund_id'] ?? null,
            'auto_remind' => $data['auto_remind'] ?? true,
            'remind_days_before' => $data['remind_days_before'] ?? 3,
        ]);

        // Mark user as having bills setup
        $user->update([
            'has_bills_setup' => true,
        ]);

        return $bill;
    }

    /**
     * Mark a bill as paid.
     */
    public function markPaid(Bill $bill, int $amount): void
    {
        $bill->markPaid($amount);
    }

    /**
     * Get bills due within the next N days for a user.
     */
    public function getBillsDueWithin(User $user, int $days = 3): \Illuminate\Database\Eloquent\Collection
    {
        $timezone = $user->timezone ?? 'Asia/Jakarta';

        return Bill::forUser($user->id)
            ->active()
            ->unpaidThisMonth()
            ->get()
            ->filter(fn($bill) => $bill->isDueWithin($days, $timezone));
    }

    /**
     * Try to find a matching bill by name/merchant for automatic payment matching.
     */
    public function findMatchingBill(User $user, string $merchantOrName): ?Bill
    {
        $bills = Bill::forUser($user->id)->active()->get();

        foreach ($bills as $bill) {
            if (stripos($merchantOrName, $bill->name) !== false
                || stripos($bill->name, $merchantOrName) !== false) {
                return $bill;
            }
        }

        return null;
    }

    /**
     * Reset this_month_paid for all bills (run on 1st of each month).
     */
    public function resetMonthlyPaymentStatus(): int
    {
        return Bill::where('this_month_paid', true)->update(['this_month_paid' => false]);
    }

    /**
     * Build upcoming bills array for AI summary context.
     */
    public function getBillsDueSoonForContext(User $user, int $days = 7): array
    {
        return $this->getBillsDueWithin($user, $days)
            ->map(fn($bill) => [
                'name' => $bill->name,
                'amount' => $bill->amount,
                'due_day' => $bill->due_day,
            ])
            ->values()
            ->toArray();
    }
}
