<?php

namespace App\Services;

/**
 * PolicyEngine — Layer 3 of the four-layer pipeline.
 *
 * Converts raw behavioral_confidence + context into a named interaction_mode.
 * The Format layer (LLM) never sees a raw confidence score — only this enum.
 * All UX behavior changes should be made here, not in prompts.
 *
 * Interaction modes:
 *   explicit_input     → user stated the account in the message
 *   auto_apply         → learned preference, consent given, apply silently
 *   soft_confirmation  → confidence 0.50–0.99, ask lightly
 *   needs_clarification → no preference, no default match
 */
class PolicyEngine
{
    // ── Interaction Mode Constants ─────────────────────────────────────

    public const MODE_EXPLICIT_INPUT      = 'explicit_input';
    public const MODE_AUTO_APPLY          = 'auto_apply';
    public const MODE_SOFT_CONFIRMATION   = 'soft_confirmation';
    public const MODE_NEEDS_CLARIFICATION = 'needs_clarification';

    /**
     * Resolve the interaction mode for account selection.
     *
     * @param  string  $accountSource  'explicit'|'learned'|'default'|'none'
     * @param  float   $confidence     behavioral_confidence for this preference
     * @param  bool    $autoApply      whether auto_apply=true on the memory row
     * @return string  one of the MODE_* constants
     */
    public function resolveInteractionMode(
        string $accountSource,
        float $confidence,
        bool $autoApply
    ): string {
        // User explicitly told us the account in this message
        if ($accountSource === 'explicit') {
            return self::MODE_EXPLICIT_INPUT;
        }

        // We have a learned preference with full consent
        if ($autoApply && $confidence >= 1.0) {
            return self::MODE_AUTO_APPLY;
        }

        // We have a preference with enough confidence to suggest
        if ($confidence >= 0.50) {
            return self::MODE_SOFT_CONFIRMATION;
        }

        // No usable preference
        return self::MODE_NEEDS_CLARIFICATION;
    }

    /**
     * Resolve the interaction mode for calorie data.
     *
     * @param  string  $calorieSource  'user_preference'|'ai_estimate'|'explicit_user'
     * @return string  'user_preference' if from memory, 'ai_estimate' otherwise
     */
    public function resolveCalorieSource(string $calorieSource): string
    {
        return $calorieSource === 'user_preference' ? 'user_preference' : 'ai_estimate';
    }
}
