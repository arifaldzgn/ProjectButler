<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RecurringEntry extends Model
{
    protected $fillable = [
        'user_id',
        'description',
        'amount',
        'type',
        'account_id',
        'category_id',
        'category_name',
        'frequency',
        'day_of_week',
        'day_of_month',
        'next_run_at',
        'last_run_at',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'amount'       => 'integer',
            'day_of_week'  => 'integer',
            'day_of_month' => 'integer',
            'next_run_at'  => 'datetime',
            'last_run_at'  => 'datetime',
            'is_active'    => 'boolean',
        ];
    }

    // ── Relationships ──────────────────────────────────────────────────

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(Fund::class, 'account_id');
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    // ── Scopes ─────────────────────────────────────────────────────────

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeDue($query)
    {
        return $query->active()->where('next_run_at', '<=', now());
    }

    public function scopeForUser($query, int $userId)
    {
        return $query->where('user_id', $userId);
    }

    // ── Helpers ────────────────────────────────────────────────────────

    /**
     * Advance next_run_at based on frequency after a successful run.
     */
    public function advanceNextRun(Carbon $from): void
    {
        $next = match ($this->frequency) {
            'daily'   => $from->copy()->addDay(),
            'weekly'  => $from->copy()->addWeek(),
            'monthly' => $from->copy()->addMonth()->setDay(
                min($this->day_of_month ?? $from->day, $from->copy()->addMonth()->daysInMonth)
            ),
            default   => $from->copy()->addMonth(),
        };

        $this->update([
            'next_run_at' => $next,
            'last_run_at' => $from,
        ]);
    }

    /**
     * Human-readable frequency label.
     */
    public function getFrequencyLabel(): string
    {
        return match ($this->frequency) {
            'daily'   => 'Setiap hari',
            'weekly'  => 'Setiap minggu',
            'monthly' => 'Setiap bulan',
            default   => $this->frequency,
        };
    }
}
