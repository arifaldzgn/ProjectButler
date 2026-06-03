<?php

namespace App\Events;

use App\Models\User;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/** Fired after the AI produces a response that is sent back to any channel. */
class AiResponseGenerated
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public readonly User    $user,
        public readonly string  $userMessage,
        public readonly string  $aiResponse,
        public readonly string  $channel,
        public readonly string  $intent,
        public readonly int     $latencyMs,
        public readonly ?string $modelUsed   = null,
        public readonly ?int    $shortcutMessageId = null,
    ) {}
}
