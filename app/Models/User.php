<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use App\Models\Account;

class User extends Authenticatable
{
    use HasFactory;

    protected $fillable = [
        'is_admin',
        'name',
        'telegram_chat_id',
        'telegram_username',
        'timezone',
        'currency',
        'preferred_language',
        // v1.2
        'tracking_mode',
        'monthly_income_idr',
        'monthly_budget_idr',
        // Goals
        'daily_budget_idr',
        'daily_calorie_goal',
        'health_goal',
        // Default spending account
        'default_account_id',
        // Notifications
        'daily_summary_enabled',
        'summary_time',
        // Onboarding
        'onboarding_step',
        'onboarding_complete_at',
        'onboarding_started_at',
        'onboarding_context',
        // Activity
        'last_active_at',
        'last_summary_sent_at',
        // Setup flags
        'has_emergency_fund',
        'has_bills_setup',
        'has_income_set',
        'has_debt_declared',
    ];

    protected $hidden = [];

    protected function casts(): array
    {
        return [
            'is_admin'              => 'boolean',
            'telegram_chat_id'      => 'integer',
            'default_account_id'    => 'integer',
            'daily_budget_idr'      => 'integer',
            'monthly_budget_idr'    => 'integer',
            'daily_calorie_goal'    => 'integer',
            'monthly_income_idr'    => 'integer',
            'daily_summary_enabled' => 'boolean',
            'onboarding_complete_at' => 'datetime',
            'onboarding_started_at' => 'datetime',
            'onboarding_context'    => 'array',
            'last_active_at'        => 'datetime',
            'last_summary_sent_at'  => 'datetime',
            'has_emergency_fund'    => 'boolean',
            'has_bills_setup'       => 'boolean',
            'has_income_set'        => 'boolean',
            'has_debt_declared'     => 'boolean',
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

    // v1.2
    public function funds(): HasMany
    {
        return $this->hasMany(Fund::class);
    }

    /**
     * All spending accounts (bank/ewallet/cash) — backed by funds table.
     * This is the relationship used by the integration guide code.
     */
    public function accounts(): HasMany
    {
        return $this->hasMany(Account::class, 'user_id')
                    ->where('fund_type', 'spending_budget');
    }

    /**
     * The default spending account set during webview onboarding.
     */
    public function defaultAccount(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Account::class, 'default_account_id');
    }

    public function bills(): HasMany
    {
        return $this->hasMany(Bill::class);
    }

    public function debts(): HasMany
    {
        return $this->hasMany(Debt::class);
    }

    public function fundTransactions(): HasMany
    {
        return $this->hasMany(FundTransaction::class);
    }

    // ── Helpers ──────────────────────────────────────────────────

    public function isAdmin(): bool
    {
        return $this->is_admin === true;
    }

    public function isOnboardingComplete(): bool
    {
        return $this->onboarding_step === 'complete';
    }

    /**
     * True if the user cannot yet use the full logging pipeline.
     * Matches Gate 3 in the integration guide.
     */
    public function isOnboardingGated(): bool
    {
        return $this->onboarding_step !== 'complete';
    }

    public function isFinanceMode(): bool
    {
        return in_array($this->tracking_mode, ['finance', 'both']);
    }

    public function isCalorieMode(): bool
    {
        return in_array($this->tracking_mode, ['calorie', 'both']);
    }

    public function getDefaultSpendingFund(): ?Fund
    {
        return $this->funds()
            ->where('is_default_spending', true)
            ->where('is_active', true)
            ->first();
    }

    /**
     * Find or create a user by Telegram chat ID.
     * New users start at 'new' — the first step of the unified state machine.
     */
    public static function findOrCreateByTelegramId(int $chatId, ?string $username = null): self
    {
        return self::firstOrCreate(
            ['telegram_chat_id' => $chatId],
            [
                'name'              => $username ?? 'User',
                'telegram_username' => $username,
                'onboarding_step'   => 'new',
                'tracking_mode'     => 'both',
            ]
        );
    }
}
