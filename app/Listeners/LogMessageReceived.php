<?php

namespace App\Listeners;

use App\Events\MessageReceived;
use App\Services\ConversationService;
use Illuminate\Contracts\Queue\ShouldQueue;

/**
 * Records every incoming message as a user turn in the conversation history.
 * Runs asynchronously so it never blocks the response path.
 */
class LogMessageReceived implements ShouldQueue
{
    public string $queue = 'low';

    public function __construct(private readonly ConversationService $conversations) {}

    public function handle(MessageReceived $event): void
    {
        $this->conversations->recordUserTurn(
            user:              $event->user,
            channel:           $event->channel,
            channelId:         $event->channelId,
            content:           $event->message,
            shortcutMessageId: $event->shortcutMessageId,
            metadata:          $event->metadata,
        );
    }
}
