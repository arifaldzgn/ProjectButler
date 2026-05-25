<?php

namespace App\Services;

use App\Models\BehavioralMemory;
use App\Models\User;
use Illuminate\Support\Facades\Log;

/**
 * BehavioralMemoryService — manages reads and writes to behavioral_memory.
 *
 * This service is stateless. All mutation happens through the Eloquent model.
 * Jobs (UpdateBehavioralMemory, ProcessBehavioralCorrection) call this service,
 * not the other way around.
 */
class BehavioralMemoryService
{
    /**
     * Resolve a learned preference for a user.
     * Returns the BehavioralMemory row if confidence >= CONF_SUGGEST_MIN, else null.
     */
    public function resolve(int $userId, string $domain, string $subject): ?BehavioralMemory
    {
        return BehavioralMemory::forUser($userId)
            ->domain($domain)
            ->subject($subject)
            ->applicable()
            ->orderByDesc('behavioral_confidence')
            ->first();
    }

    /**
     * Observe a new data point — create or strengthen an existing row.
     *
     * @param  int    $userId
     * @param  string $domain
     * @param  string $subject
     * @param  array  $value    domain-specific value array
     */
    public function observe(int $userId, string $domain, string $subject, array $value): BehavioralMemory
    {
        $existing = BehavioralMemory::forUser($userId)
            ->domain($domain)
            ->subject($subject)
            ->first();

        if ($existing) {
            $existing->strengthen();
            // Merge value updates (e.g. updated avg_grams)
            $existing->update(['value' => array_merge($existing->value ?? [], $value)]);
            return $existing->fresh();
        }

        return BehavioralMemory::create([
            'user_id'               => $userId,
            'domain'                => $domain,
            'subject'               => $subject,
            'value'                 => $value,
            'behavioral_confidence' => BehavioralMemory::CONF_STEP_UP,
            'observation_count'     => 1,
            'last_observed_at'      => now(),
        ]);
    }

    /**
     * Weaken an old preference and strengthen a new one.
     * Called by ProcessBehavioralCorrection.
     *
     * @param  int    $userId
     * @param  string $domain
     * @param  string $oldSubject   what was wrongly applied
     * @param  string $newSubject   what the user corrected to (often same subject, different value)
     * @param  array  $newValue     the correct value
     */
    public function correct(
        int $userId,
        string $domain,
        string $oldSubject,
        string $newSubject,
        array $newValue
    ): void {
        // 1. Weaken old preference
        $old = BehavioralMemory::forUser($userId)
            ->domain($domain)
            ->subject($oldSubject)
            ->first();

        if ($old) {
            $old->weaken(); // may delete if confidence hits 0
        }

        // 2. Strengthen new preference
        $this->observe($userId, $domain, $newSubject, $newValue);
    }

    /**
     * Apply consent: user said yes to auto-apply.
     * Sets auto_apply=true, confidence=1.0.
     */
    public function applyConsent(int $userId, string $domain, string $subject): void
    {
        BehavioralMemory::forUser($userId)
            ->domain($domain)
            ->subject($subject)
            ->first()
            ?->update([
                'user_consented'        => true,
                'auto_apply'            => true,
                'behavioral_confidence' => 1.0,
            ]);
    }

    /**
     * Deny consent: user said no. Delete the row. Never ask again.
     */
    public function denyConsent(int $userId, string $domain, string $subject): void
    {
        BehavioralMemory::forUser($userId)
            ->domain($domain)
            ->subject($subject)
            ->delete();
    }

    /**
     * Mark that we sent the consent gate question for this row.
     * Prevents re-asking on subsequent interactions.
     */
    public function markConsentAsked(int $userId, string $domain, string $subject): void
    {
        BehavioralMemory::forUser($userId)
            ->domain($domain)
            ->subject($subject)
            ->first()
            ?->update(['consent_asked_at' => now()]);
    }

    /**
     * Get a context block for the LLM parser (food_calories + food_portion + meal_timing).
     * Max 12 rows to keep prompt lean.
     */
    public function getParserContext(int $userId): array
    {
        return BehavioralMemory::forUser($userId)
            ->whereIn('domain', [
                BehavioralMemory::DOMAIN_FOOD_CALORIES,
                BehavioralMemory::DOMAIN_FOOD_PORTION,
                BehavioralMemory::DOMAIN_MEAL_TIMING,
            ])
            ->where('behavioral_confidence', '>=', 0.5)
            ->orderByDesc('behavioral_confidence')
            ->limit(12)
            ->get()
            ->groupBy('domain')
            ->toArray();
    }
}
