<?php

namespace App\Events;

use App\Models\User;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Fired when any client sends a message to Butler.
 * Channel adapters and the Telegram webhook both fire this event.
 */
class MessageReceived
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public readonly User    $user,
        public readonly string  $message,
        public readonly string  $channel,       // telegram|shortcut|web|android
        public readonly ?string $channelId,     // chat_id, device_id, etc.
        public readonly ?int    $shortcutMessageId = null,
        public readonly array   $metadata = [],
    ) {}
}
