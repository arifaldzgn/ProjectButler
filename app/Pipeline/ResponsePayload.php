<?php

namespace App\Pipeline;

/**
 * ResponsePayload — channel-agnostic response from the AI pipeline.
 *
 * The adapter layer converts this into channel-specific format (Telegram message,
 * JSON API response, push notification, etc.).
 *
 * Current status: constructed by ShortcutAdapter for the HTTP response.
 * Future use: MessageRouter will return this instead of calling TelegramService directly.
 */
readonly class ResponsePayload
{
    public function __construct(
        // The primary text response
        public string  $text,

        // Detected intent (populated after MessageRouter processes the message)
        public string  $intent = 'unknown',

        // AI confidence for the detected intent
        public float   $confidence = 0.0,

        // Whether the message was processed successfully
        public bool    $success = true,

        // Processing status for async flows
        public string  $status = 'processed', // pending|processed|failed

        // AI model used (for logging/debugging)
        public ?string $modelUsed = null,

        // AI latency in ms
        public int     $latencyMs = 0,

        // Structured data attached to the response (entry details, balance, etc.)
        public array   $data = [],
    ) {}

    public static function pending(): self
    {
        return new self(
            text:   'Sedang diproses...',
            status: 'pending',
        );
    }

    public static function failed(string $reason = 'Processing failed'): self
    {
        return new self(
            text:    $reason,
            success: false,
            status:  'failed',
        );
    }
}
