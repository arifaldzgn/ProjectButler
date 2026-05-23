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
        'food_item',
        'calories',
        'is_calorie_estimated',
        'note',
        'entry_time',
        'metadata',
        'ai_raw_input',
        'ai_intent',
        'ai_confidence',
        'ai_prompt_version',
        'confirmed_at',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'integer',
            'calories' => 'integer',
            'is_calorie_estimated' => 'boolean',
            'ai_confidence' => 'decimal:3',
            'metadata' => 'array',
            'entry_time' => 'datetime',
            'confirmed_at' => 'datetime',
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

    /**
     * Get formatted amount in Indonesian Rupiah style.
     */
    public function getFormattedAmountAttribute(): string
    {
        return 'Rp ' . number_format($this->amount ?? 0, 0, ',', '.');
    }
}
