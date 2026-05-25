<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Fund extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'user_id',
        'fund_type',
        'name',
        'description',
        'current_balance',
        'initial_balance',
        'target_amount',
        'target_date',
        'monthly_contribution',
        'progress_pct',
        'months_remaining',
        'on_track',
        'is_default_spending',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'current_balance' => 'integer',
            'initial_balance' => 'integer',
            'target_amount' => 'integer',
            'monthly_contribution' => 'integer',
            'target_date' => 'date',
            'progress_pct' => 'decimal:2',
            'months_remaining' => 'integer',
            'on_track' => 'boolean',
            'is_default_spending' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    // ── Relationships ──────────────────────────────────────────────────

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function fundTransactions(): HasMany
    {
        return $this->hasMany(FundTransaction::class);
    }

    public function entries(): HasMany
    {
        return $this->hasMany(Entry::class, 'source_fund_id');
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

    public function scopeByType($query, string $type)
    {
        return $query->where('fund_type', $type);
    }

    // ── Helpers ─────────────────────────────────────────────────────────

    /**
     * Credit (add money) to this fund.
     * Creates a fund_transaction record for the audit trail.
     */
    public function credit(int $amount, ?int $entryId = null, ?string $note = null): FundTransaction
    {
        $before = $this->current_balance;
        $after = $before + $amount;

        $this->update(['current_balance' => $after]);
        $this->recalculateProgress();

        return FundTransaction::create([
            'fund_id' => $this->id,
            'user_id' => $this->user_id,
            'entry_id' => $entryId,
            'transaction_type' => 'deposit',
            'amount' => $amount,
            'note' => $note,
            'balance_before' => $before,
            'balance_after' => $after,
        ]);
    }

    /**
     * Debit (remove money) from this fund.
     */
    public function debit(int $amount, ?int $entryId = null, ?string $note = null): FundTransaction
    {
        $before = $this->current_balance;
        $after = max(0, $before - $amount); // Never go below 0

        $this->update(['current_balance' => $after]);
        $this->recalculateProgress();

        return FundTransaction::create([
            'fund_id' => $this->id,
            'user_id' => $this->user_id,
            'entry_id' => $entryId,
            'transaction_type' => 'withdrawal',
            'amount' => $amount,
            'note' => $note,
            'balance_before' => $before,
            'balance_after' => $after,
        ]);
    }

    /**
     * Recalculate progress fields after a balance change.
     */
    public function recalculateProgress(): void
    {
        if (!$this->target_amount || $this->target_amount <= 0) {
            return;
        }

        $pct = round(($this->current_balance / $this->target_amount) * 100, 2);

        $monthsRemaining = null;
        $onTrack = null;

        if ($this->target_date) {
            $monthsRemaining = (int) now()->diffInMonths($this->target_date, false);
            if ($monthsRemaining > 0 && $this->monthly_contribution) {
                $projectedBalance = $this->current_balance + ($this->monthly_contribution * $monthsRemaining);
                $onTrack = $projectedBalance >= $this->target_amount;
            }
        }

        $this->update([
            'progress_pct' => $pct,
            'months_remaining' => $monthsRemaining,
            'on_track' => $onTrack,
        ]);
    }

    /**
     * Get formatted balance string.
     */
    public function getFormattedBalanceAttribute(): string
    {
        return 'Rp ' . number_format($this->current_balance, 0, ',', '.');
    }

    /**
     * Human-readable fund type label.
     */
    public function getTypeLabelAttribute(): string
    {
        return match ($this->fund_type) {
            'savings' => 'Tabungan',
            'sinking_fund' => 'Sinking Fund',
            'financial_goal' => 'Financial Goal',
            'emergency_fund' => 'Dana Darurat',
            'spending_budget' => 'Budget Belanja',
            default => 'Dana',
        };
    }
}
