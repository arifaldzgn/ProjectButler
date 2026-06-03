<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Represents a physical or logical device registered to a user.
 *
 * Each device has exactly one Sanctum token. Revoking a device revokes
 * only its token — other devices remain unaffected.
 *
 * @property int         $id
 * @property int         $user_id
 * @property string      $name
 * @property string      $platform   ios|android|desktop|web|raycast|telegram
 * @property int|null    $token_id
 * @property bool        $is_active
 * @property \Carbon\Carbon|null $last_used_at
 * @property array|null  $metadata
 */
class Device extends Model
{
    protected $fillable = [
        'user_id',
        'name',
        'platform',
        'token_id',
        'is_active',
        'last_used_at',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'is_active'    => 'boolean',
            'last_used_at' => 'datetime',
            'metadata'     => 'array',
        ];
    }

    // ── Relationships ──────────────────────────────────────────────────

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function token(): BelongsTo
    {
        return $this->belongsTo(\Laravel\Sanctum\PersonalAccessToken::class, 'token_id');
    }

    // ── Helpers ────────────────────────────────────────────────────────

    /**
     * Mark the device as used right now.
     * Called by ValidateShortcutRequest middleware on every authenticated API call.
     */
    public function touch(string $column = 'updated_at'): bool
    {
        return $this->update(['last_used_at' => now()]);
    }

    public function touchLastUsed(): void
    {
        $this->updateQuietly(['last_used_at' => now()]);
    }

    /**
     * Revoke both the device record and the associated Sanctum token.
     * Other devices belonging to the same user are not affected.
     */
    public function revoke(): void
    {
        // Delete the Sanctum token first (cascades to device.token_id = null via nullOnDelete)
        $this->token?->delete();

        $this->update(['is_active' => false]);
    }

    /**
     * Find the device associated with a given Sanctum token ID.
     */
    public static function forToken(int $tokenId): ?self
    {
        return static::where('token_id', $tokenId)->where('is_active', true)->first();
    }
}
