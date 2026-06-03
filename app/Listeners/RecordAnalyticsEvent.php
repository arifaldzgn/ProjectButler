<?php

namespace App\Listeners;

use App\Events\AiResponseGenerated;
use App\Services\AnalyticsService;
use Illuminate\Contracts\Queue\ShouldQueue;

/**
 * Increments the daily analytics counters whenever an AI response is generated.
 * Runs on the low queue — analytics latency is acceptable.
 */
class RecordAnalyticsEvent implements ShouldQueue
{
    public string $queue = 'low';

    public function __construct(private readonly AnalyticsService $analytics) {}

    public function handle(AiResponseGenerated $event): void
    {
        $this->analytics->record(
            user:      $event->user,
            channel:   $event->channel,
            intent:    $event->intent,
            latencyMs: $event->latencyMs,
        );
    }
}
