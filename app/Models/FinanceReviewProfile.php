<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FinanceReviewProfile extends Model
{
    protected $fillable = [
        'user_id',
        'domisili',
        'domisili_key',
        'gaji_bersih',
        'housing_status',
        'housing_cost',
        'cook_percentage',
        'food_base_monthly',
        'hangout_frequency',
        'transport_type',
        'commute_km_daily',
        'transport_monthly',
        'bills_snapshot',
        'recurring_snapshot',
        'tanggungan_count',
        'tanggungan_snapshot',
        'family_remittance',
        'rokok_monthly',
        'gym_monthly',
        'asuransi_monthly',
        'mudik_annual',
        'debts_snapshot',
        'last_completed_step',
        'review_completed_at',
        'ai_insights',
        'insights_generated_at',
    ];

    protected function casts(): array
    {
        return [
            'gaji_bersih'          => 'integer',
            'housing_cost'         => 'integer',
            'cook_percentage'      => 'integer',
            'food_base_monthly'    => 'integer',
            'commute_km_daily'     => 'integer',
            'transport_monthly'    => 'integer',
            'bills_snapshot'       => 'array',
            'recurring_snapshot'   => 'array',
            'tanggungan_count'     => 'integer',
            'tanggungan_snapshot'  => 'array',
            'family_remittance'    => 'integer',
            'rokok_monthly'        => 'integer',
            'gym_monthly'          => 'integer',
            'asuransi_monthly'     => 'integer',
            'mudik_annual'         => 'integer',
            'debts_snapshot'       => 'array',
            'last_completed_step'  => 'integer',
            'review_completed_at'  => 'datetime',
            'insights_generated_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function isComplete(): bool
    {
        return $this->review_completed_at !== null;
    }

    public function nextStep(): int
    {
        return min($this->last_completed_step + 1, 7);
    }
}
