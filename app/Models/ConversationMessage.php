<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A single turn (user input or assistant response) in a Conversation.
 *
 * @property int         $id
 * @property int         $conversation_id
 * @property string      $role       user|assistant|system
 * @property string      $content
 * @property string|null $intent
 * @property float|null  $confidence
 * @property int|null    $ai_latency_ms
 * @property int|null    $shortcut_message_id
 * @property array|null  $metadata
 */
class ConversationMessage extends Model
{
    public const UPDATED_AT = null; // No updated_at — messages are immutable

    protected $fillable = [
        'conversation_id',
        'role',
        'content',
        'intent',
        'confidence',
        'ai_latency_ms',
        'shortcut_message_id',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'confidence' => 'float',
            'metadata'   => 'array',
        ];
    }

    // ── Relationships ──────────────────────────────────────────────────

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(Conversation::class);
    }

    public function shortcutMessage(): BelongsTo
    {
        return $this->belongsTo(ShortcutMessage::class);
    }

    // ── Factory helpers ────────────────────────────────────────────────

    public static function userTurn(int $conversationId, string $content, array $extra = []): self
    {
        return static::create(array_merge([
            'conversation_id' => $conversationId,
            'role'            => 'user',
            'content'         => $content,
        ], $extra));
    }

    public static function assistantTurn(int $conversationId, string $content, array $extra = []): self
    {
        return static::create(array_merge([
            'conversation_id' => $conversationId,
            'role'            => 'assistant',
            'content'         => $content,
        ], $extra));
    }
}
