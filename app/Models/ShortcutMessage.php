<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Represents a message submitted by a client (iPhone Shortcut, Android, web, etc.)
 * via the public Shortcut API. Bot Token is never involved at this layer.
 *
 * @property int         $id
 * @property int         $user_id
 * @property string      $message
 * @property string      $source
 * @property string|null $response
 * @property string      $status     pending|processed|failed
 * @property array|null  $metadata
 * @property \Carbon\Carbon|null $processed_at
 */
class ShortcutMessage extends Model
{
    protected $fillable = [
        'user_id',
        'message',
        'source',
        'response',
        'status',
        'metadata',
        'processed_at',
    ];

    protected function casts(): array
    {
        return [
            'metadata'     => 'array',
            'processed_at' => 'datetime',
        ];
    }

    // ── Relationships ──────────────────────────────────────────────────

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    // ── Helpers ────────────────────────────────────────────────────────

    public function isPending(): bool
    {
        return $this->status === 'pending';
    }

    public function markProcessed(string $response): void
    {
        $this->update([
            'status'       => 'processed',
            'response'     => $response,
            'processed_at' => now(),
        ]);
    }

    public function markFailed(string $reason = 'Unknown error'): void
    {
        $this->update([
            'status'       => 'failed',
            'response'     => $reason,
            'processed_at' => now(),
        ]);
    }
}
