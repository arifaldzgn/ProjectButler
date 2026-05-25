<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Bill extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'user_id',
        'name',
        'amount',
        'currency',
        'category',
        'due_day',
        'due_day_flexibility',
        'source_fund_id',
        'is_active',
        'auto_remind',
        'remind_days_before',
        'last_paid_at',
        'last_paid_amount',
        'this_month_paid',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'integer',
            'due_day' => 'integer',
            'due_day_flexibility' => 'integer',
            'remind_days_before' => 'integer',
            'is_active' => 'boolean',
            'auto_remind' => 'boolean',
            'this_month_paid' => 'boolean',
            'last_paid_at' => 'datetime',
            'last_paid_amount' => 'integer',
        ];
    }

    // ── Relationships ──────────────────────────────────────────────────

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function sourceFund(): BelongsTo
    {
        return $this->belongsTo(Fund::class, 'source_fund_id');
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
     * Check if this bill is due within the next N days.
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
     * Get this month's due date as a Carbon instance.
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
     * Mark this bill as paid for the current month.
     */
    public function markPaid(int $amount): void
    {
        $this->update([
            'last_paid_at' => now(),
            'last_paid_amount' => $amount,
            'this_month_paid' => true,
        ]);
    }

    /**
     * Formatted amount.
     */
    public function getFormattedAmountAttribute(): string
    {
        return 'Rp ' . number_format($this->amount, 0, ',', '.');
    }
}
