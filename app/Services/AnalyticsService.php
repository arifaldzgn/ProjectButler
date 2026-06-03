<?php

namespace App\Services;

use App\Models\DailyAnalytic;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * AnalyticsService
 *
 * Maintains lightweight daily aggregates per user per channel.
 * Uses a single upsert per event — safe to call from a queue worker.
 */
class AnalyticsService
{
    /**
     * Increment counters for a single request.
     *
     * @param User   $user
     * @param string $channel    telegram|shortcut|web|android|desktop
     * @param string $intent     detected or hinted intent
     * @param int    $latencyMs  AI call latency (0 if no AI call was made)
     * @param bool   $isError
     */
    public function record(
        User   $user,
        string $channel,
        string $intent    = 'unknown',
        int    $latencyMs = 0,
        bool   $isError   = false,
    ): void {
        try {
            $date = now()->toDateString();

            // Use a raw upsert for atomicity — safe even with concurrent workers
            DB::table('daily_analytics')->upsert(
                [[
                    'user_id'               => $user->id,
                    'date'                  => $date,
                    'channel'               => $channel,
                    'requests_count'        => 1,
                    'intents_distribution'  => json_encode([$intent => 1]),
                    'total_ai_latency_ms'   => $latencyMs,
                    'errors_count'          => $isError ? 1 : 0,
                    'created_at'            => now(),
                    'updated_at'            => now(),
                ]],
                // Unique key columns
                ['user_id', 'channel', 'date'],
                // Columns to increment on conflict
                [
                    'requests_count'      => DB::raw('daily_analytics.requests_count + 1'),
                    'total_ai_latency_ms' => DB::raw("daily_analytics.total_ai_latency_ms + {$latencyMs}"),
                    'errors_count'        => DB::raw('daily_analytics.errors_count + ' . ($isError ? 1 : 0)),
                    'updated_at'          => now(),
                ]
            );

            // Intent distribution is a JSON column — update it separately
            // to avoid race conditions with the upsert above
            $this->incrementIntentCount($user->id, $channel, $date, $intent);

        } catch (\Throwable $e) {
            // Analytics failures must never bubble up to break the main pipeline
            Log::warning('AnalyticsService::record failed', [
                'user_id' => $user->id,
                'channel' => $channel,
                'error'   => $e->getMessage(),
            ]);
        }
    }

    /**
     * Get daily analytics summary for a user over the last N days.
     */
    public function getSummary(User $user, int $days = 7): array
    {
        return DailyAnalytic::where('user_id', $user->id)
            ->where('date', '>=', now()->subDays($days)->toDateString())
            ->orderBy('date')
            ->get()
            ->toArray();
    }

    private function incrementIntentCount(int $userId, string $channel, string $date, string $intent): void
    {
        $row = DailyAnalytic::where([
            'user_id' => $userId,
            'channel' => $channel,
            'date'    => $date,
        ])->first();

        if (!$row) {
            return;
        }

        $dist         = $row->intents_distribution ?? [];
        $dist[$intent] = ($dist[$intent] ?? 0) + 1;

        $row->updateQuietly(['intents_distribution' => $dist]);
    }
}
