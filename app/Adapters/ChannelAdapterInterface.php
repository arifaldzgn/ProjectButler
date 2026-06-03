<?php

namespace App\Adapters;

use App\Pipeline\MessageContext;
use App\Pipeline\ResponsePayload;

/**
 * ChannelAdapterInterface
 *
 * Defines the contract every channel adapter must implement.
 * Channel adapters handle ONLY delivery — zero business logic lives here.
 *
 * Current implementation: TelegramAdapter and ShortcutAdapter are thin wrappers.
 * Future migration: MessageRouter will be refactored to produce ResponsePayload
 * objects, then dispatch to the appropriate adapter.
 */
interface ChannelAdapterInterface
{
    /**
     * The channel identifier this adapter handles.
     * Must match Conversation.channel enum values.
     */
    public function channel(): string;

    /**
     * Send a response payload back to the originating channel.
     * The adapter is responsible for all channel-specific formatting.
     */
    public function send(MessageContext $context, ResponsePayload $payload): void;

    /**
     * Whether this adapter supports sending messages asynchronously
     * (i.e., can be dispatched to a queue independently).
     */
    public function supportsAsync(): bool;
}
