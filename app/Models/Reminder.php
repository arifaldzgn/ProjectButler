<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Reminder extends Model
{
    use SoftDeletes;

    // v1.2 reminder types
    public const TYPES = [
        'time_based',
        'behavior_based',
        'setup_incomplete',
        'bill_due',
        'debt_due',
    ];

    protected $fillable = [
        'user_id',
        'type',
        'trigger_time',
        'trigger_days',
        'trigger_condition',
        'message_template',
        'linked_bill_id',
        'linked_debt_id',
        'is_active',
        'is_system',
        'last_triggered_at',
        'trigger_count',
    ];

    protected function casts(): array
    {
        return [
            'trigger_condition' => 'array',
            'is_active' => 'boolean',
            'is_system' => 'boolean',
            'last_triggered_at' => 'datetime',
        ];
    }

    // ── Relationships ──────────────────────────────────────────────────

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function linkedBill(): BelongsTo
    {
        return $this->belongsTo(Bill::class, 'linked_bill_id');
    }

    public function linkedDebt(): BelongsTo
    {
        return $this->belongsTo(Debt::class, 'linked_debt_id');
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

    public function scopeSetupIncomplete($query)
    {
        return $query->where('type', 'setup_incomplete');
    }

    public function scopeBillDue($query)
    {
        return $query->where('type', 'bill_due');
    }

    public function scopeDebtDue($query)
    {
        return $query->where('type', 'debt_due');
    }

    public function scopeForUser($query, int $userId)
    {
        return $query->where('user_id', $userId);
    }

    // ── Helpers ─────────────────────────────────────────────────────────

    public function renderMessage(array $variables = []): string
    {
        $message = $this->message_template;
        foreach ($variables as $key => $value) {
            $message = str_replace("{{$key}}", $value, $message);
        }
        return $message;
    }

    public function markTriggered(): void
    {
        $this->update([
            'last_triggered_at' => now(),
            'trigger_count' => $this->trigger_count + 1,
        ]);
    }

    public function wasTriggeredToday(string $timezone = 'Asia/Jakarta'): bool
    {
        if (!$this->last_triggered_at) {
            return false;
        }
        return \Carbon\Carbon::parse($this->last_triggered_at)
            ->timezone($timezone)
            ->isToday();
    }
}
