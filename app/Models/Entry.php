<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Entry extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'user_id',
        'type',
        'amount',
        'currency',
        'category',
        'merchant',
        // v1.2
        'source_fund_id',
        'source_fund_confirmed',
        // Meal
        'food_item',
        'calories',
        'protein_g',
        'carbs_g',
        'fat_g',
        'is_calorie_estimated',
        // Shared
        'note',
        'entry_time',
        'metadata',
        // AI
        'ai_raw_input',
        'ai_intent',
        'ai_confidence',
        'ai_prompt_version',
        // Category
        'category_id',
        // Lifecycle
        'confirmed_at',
        // v2.1 Undo + Correction
        'undo_token',
        'undo_expires_at',
        'is_undone',
        'correction_reason',
        'telegram_message_id',
    ];

    protected function casts(): array
    {
        return [
            'amount'               => 'integer',
            'calories'             => 'integer',
            'protein_g'            => 'decimal:1',
            'carbs_g'              => 'decimal:1',
            'fat_g'                => 'decimal:1',
            'is_calorie_estimated' => 'boolean',
            'source_fund_confirmed' => 'boolean',
            'ai_confidence'        => 'decimal:3',
            'metadata'             => 'array',
            'entry_time'           => 'datetime',
            'confirmed_at'         => 'datetime',
            // v2.1
            'undo_expires_at'      => 'datetime',
            'is_undone'            => 'boolean',
            'telegram_message_id'  => 'integer',
        ];
    }

    // Valid entry types (v1.2 expanded)
    public const TYPES = [
        'expense',
        'meal',
        'saving',
        'income',
        'bill_payment',
        'sinking_fund_deposit',
        'goal_deposit',
        'debt_payment',
        'transfer',
    ];

    // ── Relationships ──────────────────────────────────────────────────

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function sourceFund(): BelongsTo
    {
        return $this->belongsTo(Fund::class, 'source_fund_id');
    }

    public function customCategory(): BelongsTo
    {
        return $this->belongsTo(Category::class, 'category_id');
    }

    public function fundTransactions()
    {
        return $this->hasMany(FundTransaction::class);
    }

    // ── Scopes ─────────────────────────────────────────────────────────

    public function scopeForUser($query, int $userId)
    {
        return $query->where('user_id', $userId);
    }

    public function scopeExpenses($query)
    {
        return $query->where('type', 'expense');
    }

    public function scopeMeals($query)
    {
        return $query->where('type', 'meal');
    }

    public function scopeSavings($query)
    {
        return $query->where('type', 'saving');
    }

    public function scopeIncome($query)
    {
        return $query->where('type', 'income');
    }

    public function scopeBillPayments($query)
    {
        return $query->where('type', 'bill_payment');
    }

    public function scopeDebtPayments($query)
    {
        return $query->where('type', 'debt_payment');
    }

    public function scopeConfirmed($query)
    {
        return $query->whereNotNull('confirmed_at');
    }

    public function scopePending($query)
    {
        return $query->whereNull('confirmed_at');
    }

    public function scopeForDate($query, string $date)
    {
        return $query->whereDate('entry_time', $date);
    }

    public function scopeForMonth($query, int $year, int $month)
    {
        return $query->whereYear('entry_time', $year)
                     ->whereMonth('entry_time', $month);
    }

    // ── Helpers ─────────────────────────────────────────────────────────

    public function isConfirmed(): bool
    {
        return $this->confirmed_at !== null;
    }

    public function isPending(): bool
    {
        return $this->confirmed_at === null;
    }

    public function isFinancial(): bool
    {
        return in_array($this->type, ['expense', 'saving', 'income', 'bill_payment', 'sinking_fund_deposit', 'goal_deposit', 'debt_payment']);
    }

    public function getFormattedAmountAttribute(): string
    {
        return 'Rp ' . number_format($this->amount ?? 0, 0, ',', '.');
    }

    // ── v2.1 Undo Helpers ──────────────────────────────────────────────

    /**
     * Whether this entry can still be undone (within 5-minute window).
     */
    public function isUndoable(): bool
    {
        return !$this->is_undone
            && $this->undo_expires_at !== null
            && $this->undo_expires_at->isFuture();
    }

    /**
     * Mark this entry as undone and expire the undo window.
     */
    public function markUndone(): void
    {
        $this->update([
            'is_undone'      => true,
            'deleted_at'     => now(),
            'undo_expires_at' => now(), // collapse the window
        ]);
    }
}
