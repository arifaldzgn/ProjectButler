<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DailySummary extends Model
{
    /**
     * Only created_at is stored (no updated_at column).
     */
    const UPDATED_AT = null;

    protected $fillable = [
        'user_id',
        'summary_date',
        'summary_type',
        'total_spent_idr',
        'total_calories',
        'total_saved_idr',
        'entry_count',
        'budget_remaining',
        'streak_at_time',
        'ai_generated_text',
        'ai_prompt_version',
        'was_delivered',
        'delivered_at',
        'delivery_error',
    ];

    protected function casts(): array
    {
        return [
            'summary_date' => 'date',
            'was_delivered' => 'boolean',
            'delivered_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    // ── Scopes ─────────────────────────────────────────────────────────

    public function scopeForUser($query, int $userId)
    {
        return $query->where('user_id', $userId);
    }

    public function scopeDelivered($query)
    {
        return $query->where('was_delivered', true);
    }

    public function scopeDaily($query)
    {
        return $query->where('summary_type', 'daily');
    }
}
