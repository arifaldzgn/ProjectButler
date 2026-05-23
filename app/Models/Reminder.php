<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Reminder extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'user_id',
        'type',
        'trigger_time',
        'trigger_days',
        'trigger_condition',
        'message_template',
        'is_active',
        'last_triggered_at',
        'trigger_count',
    ];

    protected function casts(): array
    {
        return [
            'trigger_condition' => 'array',
            'is_active' => 'boolean',
            'last_triggered_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    // ── Scopes ─────────────────────────────────────────────────────────

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeTimeBased($query)
    {
        return $query->where('type', 'time_based');
    }

    public function scopeBehaviorBased($query)
    {
        return $query->where('type', 'behavior_based');
    }

    public function scopeForUser($query, int $userId)
    {
        return $query->where('user_id', $userId);
    }

    // ── Helpers ─────────────────────────────────────────────────────────

    /**
     * Render the message template with variables.
     */
    public function renderMessage(array $variables = []): string
    {
        $message = $this->message_template;

        foreach ($variables as $key => $value) {
            $message = str_replace("{{$key}}", $value, $message);
        }

        return $message;
    }

    /**
     * Record that this reminder was triggered.
     */
    public function markTriggered(): void
    {
        $this->update([
            'last_triggered_at' => now(),
            'trigger_count' => $this->trigger_count + 1,
        ]);
    }
}
