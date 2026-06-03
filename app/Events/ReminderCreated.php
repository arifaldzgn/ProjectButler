<?php

namespace App\Events;

use App\Models\Reminder;
use App\Models\User;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/** Fired when a reminder is created by any channel. */
class ReminderCreated
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public readonly User     $user,
        public readonly Reminder $reminder,
        public readonly string   $channel,
    ) {}
}
