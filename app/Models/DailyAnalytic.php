<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Daily analytics aggregates — one row per user per channel per day.
 * Written via AnalyticsService::record() using upsert for efficiency.
 *
 * @property int    $id
 * @property int    $user_id
 * @property string $date              YYYY-MM-DD
 * @property string $channel
 * @property int    $requests_count
 * @property array  $intents_distribution   {"log_expense":5,"log_meal":2}
 * @property int    $total_ai_latency_ms
 * @property int    $errors_count
 */
class DailyAnalytic extends Model
{
    protected $fillable = [
        'user_id',
        'date',
        'channel',
        'requests_count',
        'intents_distribution',
        'total_ai_latency_ms',
        'errors_count',
    ];

    protected function casts(): array
    {
        return [
            'date'                  => 'date',
            'intents_distribution'  => 'array',
            'requests_count'        => 'integer',
            'total_ai_latency_ms'   => 'integer',
            'errors_count'          => 'integer',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Computed average AI latency in ms for this row.
     */
    public function getAvgAiLatencyMsAttribute(): ?float
    {
        if ($this->requests_count === 0) {
            return null;
        }
        return round($this->total_ai_latency_ms / $this->requests_count, 1);
    }
}
