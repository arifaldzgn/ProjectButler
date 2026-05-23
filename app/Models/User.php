<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;

class User extends Authenticatable
{
    use HasFactory;

    protected $fillable = [
        'name',
        'telegram_chat_id',
        'telegram_username',
        'timezone',
        'preferred_language',
        'daily_budget_idr',
        'daily_calorie_goal',
        'onboarding_step',
        'onboarding_complete_at',
        'last_active_at',
        'last_summary_sent_at',
    ];

    protected $hidden = [];

    protected function casts(): array
    {
        return [
            'telegram_chat_id' => 'integer',
            'daily_budget_idr' => 'integer',
            'daily_calorie_goal' => 'integer',
            'onboarding_complete_at' => 'datetime',
            'last_active_at' => 'datetime',
            'last_summary_sent_at' => 'datetime',
        ];
    }

    // ── Relationships ──────────────────────────────────────────────────

    public function entries(): HasMany
    {
        return $this->hasMany(Entry::class);
    }

    public function streak(): HasOne
    {
        return $this->hasOne(Streak::class);
    }

    public function reminders(): HasMany
    {
        return $this->hasMany(Reminder::class);
    }

    public function dailySummaries(): HasMany
    {
        return $this->hasMany(DailySummary::class);
    }

    public function aiLogs(): HasMany
    {
        return $this->hasMany(AiLog::class);
    }

    // ── Helpers ─────────────────────────────────────────────────────────

    public function isOnboardingComplete(): bool
    {
        return $this->onboarding_step === 'complete';
    }

    /**
     * Find or create a user by Telegram chat ID.
     */
    public static function findOrCreateByTelegramId(int $chatId, ?string $username = null): self
    {
        return self::firstOrCreate(
            ['telegram_chat_id' => $chatId],
            [
                'name' => $username ?? 'User',
                'telegram_username' => $username,
                'onboarding_step' => 'new',
            ]
        );
    }
}
