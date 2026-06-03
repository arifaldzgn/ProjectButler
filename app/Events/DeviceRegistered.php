<?php

namespace App\Events;

use App\Models\Device;
use App\Models\User;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/** Fired when a new device is successfully paired via PairingController. */
class DeviceRegistered
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public readonly User   $user,
        public readonly Device $device,
    ) {}
}
