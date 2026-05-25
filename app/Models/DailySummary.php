<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DailySummary extends Model
{
    const UPDATED_AT = null;

    protected $fillable = [
        'user_id',
        'summary_date',
        'summary_type',
        // Finance
        'total_spent_idr',
        'total_income_idr',
        'total_bills_paid',
        'total_saved_idr',
        'budget_remaining',
        'free_balance_eod',
        // Calories
        'total_calories',
        'calorie_goal',
        'calorie_status',
        // Funds & upcoming
        'funds_snapshot',
        'bills_due_this_week',
        'debts_due_this_week',
        // Engagement
        'entry_count',
        'streak_at_time',
        // AI
        'ai_generated_text',
        'ai_prompt_version',
        // Delivery
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
            'funds_snapshot' => 'array',
            'bills_due_this_week' => 'array',
            'debts_due_this_week' => 'array',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

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
