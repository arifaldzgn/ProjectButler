<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Debt extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'user_id',
        'name',
        'debt_type',
        'total_amount',
        'monthly_installment',
        'interest_rate',
        'remaining_balance',
        'start_date',
        'due_day',
        'end_date',
        'is_active',
        'auto_remind',
        'remind_days_before',
        'last_paid_at',
        'this_month_paid',
    ];

    protected function casts(): array
    {
        return [
            'total_amount' => 'integer',
            'monthly_installment' => 'integer',
            'remaining_balance' => 'integer',
            'interest_rate' => 'decimal:2',
            'start_date' => 'date',
            'end_date' => 'date',
            'due_day' => 'integer',
            'remind_days_before' => 'integer',
            'is_active' => 'boolean',
            'auto_remind' => 'boolean',
            'this_month_paid' => 'boolean',
            'last_paid_at' => 'datetime',
        ];
    }

    // ── Relationships ──────────────────────────────────────────────────

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    // ── Scopes ─────────────────────────────────────────────────────────

    public function scopeForUser($query, int $userId)
    {
        return $query->where('user_id', $userId);
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeUnpaidThisMonth($query)
    {
        return $query->where('this_month_paid', false);
    }

    // ── Helpers ─────────────────────────────────────────────────────────

    /**
     * Check if this debt payment is due within the next N days.
     */
    public function isDueWithin(int $days, string $timezone = 'Asia/Jakarta'): bool
    {
        $today = Carbon::now($timezone);
        $dueDate = $this->getDueDateThisMonth($timezone);

        if ($dueDate === null) {
            return false;
        }

        $daysUntilDue = $today->diffInDays($dueDate, false);
        return $daysUntilDue >= 0 && $daysUntilDue <= $days;
    }

    /**
     * Get this month's due date.
     */
    public function getDueDateThisMonth(string $timezone = 'Asia/Jakarta'): ?Carbon
    {
        try {
            $now = Carbon::now($timezone);
            $day = min($this->due_day, $now->daysInMonth);
            return Carbon::create($now->year, $now->month, $day, 0, 0, 0, $timezone);
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * Record a payment and reduce remaining balance.
     */
    public function recordPayment(int $amount): void
    {
        $newBalance = max(0, $this->remaining_balance - $amount);

        $this->update([
            'remaining_balance' => $newBalance,
            'last_paid_at' => now(),
            'this_month_paid' => true,
            'is_active' => $newBalance > 0,
        ]);
    }

    /**
     * Get formatted monthly installment.
     */
    public function getFormattedInstallmentAttribute(): string
    {
        return 'Rp ' . number_format($this->monthly_installment, 0, ',', '.');
    }

    /**
     * Human-readable debt type.
     */
    public function getTypeLabelAttribute(): string
    {
        return match ($this->debt_type) {
            'installment' => 'Cicilan',
            'credit_card' => 'Kartu Kredit',
            'personal_loan' => 'Pinjaman Pribadi',
            'mortgage' => 'KPR',
            'paylater' => 'Paylater',
            default => 'Utang',
        };
    }
}
