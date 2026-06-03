<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A conversation thread, channel-agnostic.
 *
 * One conversation spans multiple messages across any channel.
 * Future mobile apps can continue conversations started in Telegram.
 *
 * @property int         $id
 * @property int         $user_id
 * @property string      $channel    telegram|shortcut|web|android|desktop
 * @property string|null $channel_id Telegram chat_id, device_id, etc.
 * @property string|null $title
 * @property string      $status     active|archived
 * @property array|null  $metadata
 */
class Conversation extends Model
{
    protected $fillable = [
        'user_id',
        'channel',
        'channel_id',
        'title',
        'status',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'metadata' => 'array',
        ];
    }

    // ── Relationships ──────────────────────────────────────────────────

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function messages(): HasMany
    {
        return $this->hasMany(ConversationMessage::class)->orderBy('created_at');
    }

    // ── Helpers ────────────────────────────────────────────────────────

    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    public function archive(): void
    {
        $this->update(['status' => 'archived']);
    }

    /**
     * Find or create the active conversation for a user on a given channel+id.
     * This is the primary entry point for ConversationService.
     */
    public static function findOrStartFor(int $userId, string $channel, ?string $channelId = null): self
    {
        return static::firstOrCreate(
            [
                'user_id'    => $userId,
                'channel'    => $channel,
                'channel_id' => $channelId,
                'status'     => 'active',
            ],
            [
                'title'    => null,
                'metadata' => [],
            ]
        );
    }
}
