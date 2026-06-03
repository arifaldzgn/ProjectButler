<?php

namespace App\Pipeline;

/**
 * MessageContext — immutable value object passed through the pipeline.
 *
 * Carries everything needed to process a message: the raw input, the resolved user,
 * channel metadata, and optional hints the client provides.
 *
 * This DTO is the foundation for the future unified message pipeline.
 * Current usage: constructed by ShortcutMessageService and ProcessTelegramMessage.
 */
readonly class MessageContext
{
    public function __construct(
        // Who sent the message
        public int     $userId,

        // The raw text input
        public string  $message,

        // Which channel delivered this (telegram|shortcut|web|android|desktop)
        public string  $channel,

        // Channel-specific conversation ID (Telegram chat_id, device_id, etc.)
        public ?string $channelId = null,

        // Optional intent hint from the client (skips AI parse if high-confidence)
        public ?string $intentHint = null,

        // Shortcut message ID for response relay (null for Telegram)
        public ?int    $shortcutMessageId = null,

        // Device ID if the request came from a registered device
        public ?int    $deviceId = null,

        // Arbitrary extra metadata (device model, OS, shortcut version, etc.)
        public array   $metadata = [],
    ) {}

    public function hasIntentHint(): bool
    {
        return $this->intentHint !== null && $this->intentHint !== '';
    }

    public function isFromShortcut(): bool
    {
        return $this->channel === 'shortcut';
    }

    public function isFromTelegram(): bool
    {
        return $this->channel === 'telegram';
    }

    /**
     * Build the tagged message string used by ProcessTelegramMessage.
     * Format: "__shortcut:{id}|intent:{hint}|{message}" or "__shortcut:{id}|{message}"
     */
    public function toTaggedMessage(): string
    {
        if (!$this->shortcutMessageId) {
            return $this->message;
        }

        $tag = "__shortcut:{$this->shortcutMessageId}";
        if ($this->intentHint) {
            $tag .= "|intent:{$this->intentHint}";
        }
        return "{$tag}|{$this->message}";
    }
}
