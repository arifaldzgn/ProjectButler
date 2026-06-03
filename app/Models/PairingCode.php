<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

/**
 * A short-lived one-time code used to pair a device without administrator intervention.
 *
 * Flow:
 *   1. User calls POST /api/pair/request  → PairingCode created with 6-char code + QR data
 *   2. User enters code into iPhone Shortcut or pastes URL
 *   3. Shortcut calls POST /api/pair/claim → PairingCode.claimed_at set, Device + Token created
 *
 * @property int         $id
 * @property int         $user_id
 * @property string      $code       6-character alphanumeric (uppercase)
 * @property string|null $device_name
 * @property string      $platform
 * @property \Carbon\Carbon $expires_at
 * @property \Carbon\Carbon|null $claimed_at
 * @property int|null    $device_id
 */
class PairingCode extends Model
{
    protected $fillable = [
        'user_id',
        'code',
        'device_name',
        'platform',
        'expires_at',
        'claimed_at',
        'device_id',
    ];

    protected function casts(): array
    {
        return [
            'expires_at' => 'datetime',
            'claimed_at' => 'datetime',
        ];
    }

    // ── Relationships ──────────────────────────────────────────────────

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function device(): BelongsTo
    {
        return $this->belongsTo(Device::class);
    }

    // ── Helpers ────────────────────────────────────────────────────────

    public function isExpired(): bool
    {
        return $this->expires_at->isPast();
    }

    public function isClaimed(): bool
    {
        return $this->claimed_at !== null;
    }

    public function isUsable(): bool
    {
        return !$this->isClaimed() && !$this->isExpired();
    }

    /**
     * Generate a unique 6-character uppercase alphanumeric code.
     * Excludes ambiguous chars (0, O, I, L) for readability.
     */
    public static function generateCode(): string
    {
        $chars = 'ABCDEFGHJKMNPQRSTUVWXYZ23456789';
        do {
            $code = '';
            for ($i = 0; $i < 6; $i++) {
                $code .= $chars[random_int(0, strlen($chars) - 1)];
            }
        } while (static::where('code', $code)->exists());

        return $code;
    }
}
