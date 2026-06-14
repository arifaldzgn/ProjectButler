<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BehavioralMemory extends Model
{
    protected $table = 'behavioral_memory';

    protected $fillable = [
        'user_id',
        'domain',
        'subject',
        'value',
        'context_json',
        'behavioral_confidence',
        'observation_count',
        'user_consented',
        'auto_apply',
        'consent_asked_at',
        'last_observed_at',
    ];

    protected function casts(): array
    {
        return [
            'value'                  => 'array',
            'context_json'           => 'array',
            'behavioral_confidence'  => 'decimal:3',
            'observation_count'      => 'integer',
            'user_consented'         => 'boolean',
            'auto_apply'             => 'boolean',
            'consent_asked_at'       => 'datetime',
            'last_observed_at'       => 'datetime',
        ];
    }

    // ── Domain Constants ───────────────────────────────────────────────

    public const DOMAIN_MERCHANT_ACCOUNT  = 'merchant_account';
    public const DOMAIN_FOOD_CALORIES     = 'food_calories';
    public const DOMAIN_FOOD_PORTION      = 'food_portion';
    public const DOMAIN_MEAL_TIMING       = 'meal_timing';
    public const DOMAIN_CATEGORY_ACCOUNT  = 'category_account';
    public const DOMAIN_SPEND_RHYTHM      = 'spend_rhythm';
    public const DOMAIN_LOG_TIMING        = 'log_timing';     // tracks typical logging hours
    public const DOMAIN_TRANSFER_SOURCE   = 'transfer_source'; // the account a user usually sends transfers FROM

    // Confidence thresholds
    public const CONF_SUGGEST_MIN   = 0.50;
    public const CONF_CONSENT_MIN   = 0.80;
    public const CONF_AUTO_APPLY    = 1.00;
    public const CONF_STEP_UP       = 0.10;
    public const CONF_STEP_DOWN     = 0.30;

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

    public function scopeDomain($query, string $domain)
    {
        return $query->where('domain', $domain);
    }

    public function scopeSubject($query, string $subject)
    {
        return $query->where('subject', $subject);
    }

    public function scopeApplicable($query)
    {
        return $query->where('behavioral_confidence', '>=', self::CONF_SUGGEST_MIN);
    }

    public function scopeAutoApply($query)
    {
        return $query->where('auto_apply', true)
                     ->where('behavioral_confidence', '>=', self::CONF_AUTO_APPLY);
    }

    // ── Helpers ────────────────────────────────────────────────────────

    public function canAutoApply(): bool
    {
        return $this->auto_apply && $this->behavioral_confidence >= self::CONF_AUTO_APPLY;
    }

    public function shouldAskConsent(): bool
    {
        return !$this->user_consented
            && is_null($this->consent_asked_at)
            && $this->behavioral_confidence >= self::CONF_CONSENT_MIN;
    }

    public function strengthen(): void
    {
        $newConf = min(1.0, (float) $this->behavioral_confidence + self::CONF_STEP_UP);
        $this->update([
            'behavioral_confidence' => $newConf,
            'observation_count'     => $this->observation_count + 1,
            'last_observed_at'      => now(),
        ]);
    }

    public function weaken(): void
    {
        $newConf = max(0.0, (float) $this->behavioral_confidence - self::CONF_STEP_DOWN);
        if ($newConf <= 0.0) {
            $this->delete();
            return;
        }
        $this->update([
            'behavioral_confidence' => $newConf,
            'last_observed_at'      => now(),
        ]);
    }
}
