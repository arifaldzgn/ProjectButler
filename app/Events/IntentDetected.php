<?php

namespace App\Events;

use App\Models\User;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/** Fired after the AI (or intent hint) determines what the user wants. */
class IntentDetected
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public readonly User   $user,
        public readonly string $message,
        public readonly string $intent,
        public readonly float  $confidence,
        public readonly string $channel,
        public readonly int    $aiLatencyMs = 0,
        public readonly bool   $fromHint    = false, // true = client provided intent, no AI call
    ) {}
}
