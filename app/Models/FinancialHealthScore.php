<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FinancialHealthScore extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'user_id',
        'score',
        'components',
        'calculated_at',
    ];

    protected function casts(): array
    {
        return [
            'score'          => 'integer',
            'components'     => 'array',
            'calculated_at'  => 'datetime',
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

    public function scopeLatest($query)
    {
        return $query->orderByDesc('calculated_at');
    }

    // ── Helpers ────────────────────────────────────────────────────────

    /**
     * Health band label and color.
     */
    public function getBand(): array
    {
        return match (true) {
            $this->score >= 80 => ['label' => 'Excellent', 'color' => '#10b981', 'emoji' => '🟢'],
            $this->score >= 60 => ['label' => 'Good',      'color' => '#22c55e', 'emoji' => '🟡'],
            $this->score >= 40 => ['label' => 'Fair',      'color' => '#f97316', 'emoji' => '🟠'],
            default            => ['label' => 'Needs Work', 'color' => '#ef4444', 'emoji' => '🔴'],
        };
    }
}
