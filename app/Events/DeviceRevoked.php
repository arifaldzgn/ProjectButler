<?php

namespace App\Events;

use App\Models\Device;
use App\Models\User;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/** Fired when a device is revoked by the user (e.g. lost phone). */
class DeviceRevoked
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public readonly User   $user,
        public readonly Device $device, // device is already inactive when event fires
    ) {}
}
