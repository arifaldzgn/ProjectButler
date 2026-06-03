<?php

namespace App\Services;

use App\Models\Conversation;
use App\Models\ConversationMessage;
use App\Models\User;
use Illuminate\Support\Facades\Log;

/**
 * ConversationService
 *
 * Manages conversation threads across all channels.
 * Additive — does not touch MessageRouter or TelegramService internals.
 *
 * Usage:
 *   - recordUserTurn()    → called by LogMessageReceived listener
 *   - recordAssistantTurn() → called by ProcessTelegramMessage after handle()
 */
class ConversationService
{
    /**
     * Find or create the active conversation for a channel+id pair,
     * then append a user-role message turn.
     */
    public function recordUserTurn(
        User    $user,
        string  $channel,
        ?string $channelId,
        string  $content,
        ?int    $shortcutMessageId = null,
        array   $metadata = [],
    ): ConversationMessage {
        $conversation = Conversation::findOrStartFor($user->id, $channel, $channelId);

        return ConversationMessage::userTurn($conversation->id, $content, [
            'shortcut_message_id' => $shortcutMessageId,
            'metadata'            => $metadata ?: null,
        ]);
    }

    /**
     * Append an assistant-role message turn.
     * Called after the AI pipeline produces a response.
     */
    public function recordAssistantTurn(
        User    $user,
        string  $channel,
        ?string $channelId,
        string  $content,
        array   $extra = [],
    ): ConversationMessage {
        $conversation = Conversation::findOrStartFor($user->id, $channel, $channelId);

        return ConversationMessage::assistantTurn($conversation->id, $content, $extra);
    }

    /**
     * Retrieve the last N messages from a user's active conversation on a channel.
     * Useful for building AI context windows in future implementations.
     *
     * @return \Illuminate\Database\Eloquent\Collection<ConversationMessage>
     */
    public function getRecentHistory(User $user, string $channel, ?string $channelId, int $limit = 10)
    {
        $conversation = Conversation::where([
            'user_id'    => $user->id,
            'channel'    => $channel,
            'channel_id' => $channelId,
            'status'     => 'active',
        ])->first();

        if (!$conversation) {
            return collect();
        }

        return $conversation->messages()
            ->orderBy('created_at', 'desc')
            ->limit($limit)
            ->get()
            ->reverse()
            ->values();
    }
}
