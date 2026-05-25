<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;

/**
 * Account — a scoped Eloquent model representing a user's spending accounts.
 *
 * The integration guide uses `Account::create()` and `$user->accounts()`.
 * Our physical storage is the `funds` table. An "Account" in the webview
 * context is any fund where `fund_type = 'spending_budget'` (the default
 * created during onboarding for bank accounts, e-wallets, and cash).
 *
 * We use a global scope to keep this transparent — guide code works as-is.
 *
 * Mapped columns (guide name → funds table name):
 *   current_balance  → current_balance  ✓
 *   type             → stored in metadata / type helper below
 *   is_default       → is_default_spending
 */
class Account extends Fund
{
    protected $table = 'funds';

    /**
     * Boot: apply global scope so Account always acts on spending_budget funds only.
     */
    protected static function booted(): void
    {
        static::addGlobalScope('spending_type', function (Builder $query) {
            $query->where('fund_type', 'spending_budget');
        });

        // Auto-set fund_type on create
        static::creating(function (self $account) {
            $account->fund_type = 'spending_budget';
        });
    }

    /**
     * Map the webview `type` field (bank/ewallet/cash/other) into
     * our metadata JSON so we can display it in the UI.
     */
    public function setTypeAttribute(string $value): void
    {
        $meta = $this->metadata ?? [];
        $meta['account_type'] = $value;
        $this->metadata = $meta;
    }

    public function getTypeAttribute(): ?string
    {
        return $this->metadata['account_type'] ?? null;
    }

    public function getIsDefaultAttribute(): bool
    {
        return (bool) $this->is_default_spending;
    }

    public function setIsDefaultAttribute(bool $value): void
    {
        $this->is_default_spending = $value;
    }
}
