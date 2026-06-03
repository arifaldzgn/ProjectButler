<?php

namespace App\Adapters;

use App\Pipeline\MessageContext;
use App\Pipeline\ResponsePayload;
use App\Services\TelegramService;
use Illuminate\Support\Facades\Log;

/**
 * TelegramAdapter — thin wrapper around TelegramService.
 *
 * Currently a no-op pass-through: all business logic remains in MessageRouter.
 * This class exists as a migration hook — Phase 2 will move TelegramService calls
 * out of MessageRouter and into this adapter.
 *
 * DO NOT add business logic here yet.
 */
class TelegramAdapter implements ChannelAdapterInterface
{
    public function __construct(private readonly TelegramService $telegram) {}

    public function channel(): string
    {
        return 'telegram';
    }

    /**
     * Send a text response to a Telegram chat.
     * Context channelId = Telegram chat_id.
     */
    public function send(MessageContext $context, ResponsePayload $payload): void
    {
        if (!$context->channelId) {
            Log::warning('TelegramAdapter::send called with no channelId');
            return;
        }

        $this->telegram->sendMessage($context->channelId, $payload->text);
    }

    public function supportsAsync(): bool
    {
        return true; // Telegram messages are always sent from queue workers
    }
}
