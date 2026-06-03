<?php

namespace App\Adapters;

use App\Pipeline\MessageContext;
use App\Pipeline\ResponsePayload;
use App\Services\ShortcutMessageService;

/**
 * ShortcutAdapter — delivers responses to Shortcut API callers.
 *
 * In sync mode: relays response through the cache so the HTTP controller
 * can return it in the same request.
 * In async mode: persists response to shortcut_messages table only.
 *
 * This adapter is the counterpart to TelegramAdapter for non-Telegram channels.
 */
class ShortcutAdapter implements ChannelAdapterInterface
{
    public function __construct(private readonly ShortcutMessageService $shortcutService) {}

    public function channel(): string
    {
        return 'shortcut';
    }

    /**
     * Relay the response back to the waiting HTTP controller (sync)
     * or persist it for later polling (async).
     */
    public function send(MessageContext $context, ResponsePayload $payload): void
    {
        if (!$context->shortcutMessageId) {
            return;
        }

        $this->shortcutService->relayResponse(
            $context->shortcutMessageId,
            $payload->text
        );
    }

    public function supportsAsync(): bool
    {
        return true;
    }
}
