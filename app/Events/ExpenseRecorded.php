<?php

namespace App\Events;

use App\Models\Entry;
use App\Models\User;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/** Fired when an expense entry is confirmed and persisted. */
class ExpenseRecorded
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public readonly User  $user,
        public readonly Entry $entry,
        public readonly int   $todayTotalIdr,
        public readonly ?int  $budgetRemainingIdr,
    ) {}
}
