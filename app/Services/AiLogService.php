<?php

namespace App\Services;

use App\Models\AiLog;
use App\Models\User;

class AiLogService
{
    /**
     * Log an AI call.
     *
     * Per spec: log [user_id, intent, confidence, latency_ms, tokens_used] for every AI call.
     */
    public function logCall(array $params): AiLog
    {
        return AiLog::create([
            'user_id' => $params['user_id'] ?? null,
            'entry_id' => $params['entry_id'] ?? null,
            'call_type' => $params['call_type'],
            'prompt_version' => $params['prompt_version'],
            'raw_input' => $params['raw_input'],
            'token_count_input' => $params['token_count_input'] ?? null,
            'raw_output' => $params['raw_output'],
            'token_count_output' => $params['token_count_output'] ?? null,
            'intent_detected' => $params['intent_detected'] ?? null,
            'confidence_score' => $params['confidence_score'] ?? null,
            'latency_ms' => $params['latency_ms'],
            'was_successful' => $params['was_successful'] ?? true,
            'error_message' => $params['error_message'] ?? null,
        ]);
    }

    /**
     * Log a parse call with result data.
     */
    public function logParseCall(
        User $user,
        string $rawInput,
        ?array $result,
        int $latencyMs,
        bool $success = true,
        ?string $error = null,
        ?array $tokenUsage = null
    ): AiLog {
        return $this->logCall([
            'user_id' => $user->id,
            'call_type' => 'parse',
            'prompt_version' => 'parse_v1',
            'raw_input' => $rawInput,
            'raw_output' => $result ? json_encode($result) : '',
            'intent_detected' => $result['intent'] ?? null,
            'confidence_score' => $result['confidence'] ?? null,
            'latency_ms' => $latencyMs,
            'was_successful' => $success,
            'error_message' => $error,
            'token_count_input' => $tokenUsage['input'] ?? null,
            'token_count_output' => $tokenUsage['output'] ?? null,
        ]);
    }

    /**
     * Log a message that Butler couldn't confidently route.
     *
     * Reuses the existing `ai_logs` table — flagged distinctly by
     * setting `intent_detected = 'unrecognized'` so the admin page
     * can filter on it without a schema change.
     *
     * @param array $suggestion Output from CommandSuggestionService::suggest() (or null).
     * @param string $reason    One of: 'parse_exception', 'low_confidence', 'unknown_intent'
     */
    public function logUnrecognized(
        ?User $user,
        string $rawInput,
        ?float $confidence,
        ?array $suggestion,
        string $reason
    ): AiLog {
        $payload = [
            'reason'     => $reason,
            'suggestion' => $suggestion,
        ];

        return $this->logCall([
            'user_id'          => $user?->id,
            'call_type'        => 'parse',
            'prompt_version'   => 'fallback_v1',
            'raw_input'        => $rawInput,
            'raw_output'       => $suggestion ? json_encode($suggestion) : '',
            'intent_detected'  => 'unrecognized',
            'confidence_score' => $confidence,
            'latency_ms'       => 0,   // hook is post-parse; not a separate AI call (unless stage-2 fires)
            'was_successful'   => false,
            'error_message'    => json_encode($payload),
        ]);
    }

    /**
     * Log a summary generation call.
     */
    public function logSummaryCall(
        User $user,
        string $contextJson,
        ?string $summaryText,
        int $latencyMs,
        bool $success = true,
        ?string $error = null,
        ?array $tokenUsage = null
    ): AiLog {
        return $this->logCall([
            'user_id' => $user->id,
            'call_type' => 'summary',
            'prompt_version' => 'summary_daily_v1',
            'raw_input' => $contextJson,
            'raw_output' => $summaryText ?? '',
            'latency_ms' => $latencyMs,
            'was_successful' => $success,
            'error_message' => $error,
            'token_count_input' => $tokenUsage['input'] ?? null,
            'token_count_output' => $tokenUsage['output'] ?? null,
        ]);
    }
}
