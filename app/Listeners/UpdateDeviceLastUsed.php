<?php

namespace App\Listeners;

use App\Events\DeviceRegistered;
use App\Events\MessageReceived;
use App\Models\Device;
use Illuminate\Contracts\Queue\ShouldQueue;

/**
 * Keeps Device.last_used_at current on every MessageReceived event.
 * Only applies to shortcut/API channels (Telegram devices are virtual).
 */
class UpdateDeviceLastUsed implements ShouldQueue
{
    public string $queue = 'low';

    public function handle(MessageReceived $event): void
    {
        if ($event->channel === 'telegram') {
            return; // Telegram users don't have device records
        }

        // channelId for shortcut channel = device_id stored in metadata
        $deviceId = $event->metadata['device_id'] ?? null;
        if (!$deviceId) {
            return;
        }

        Device::where('id', $deviceId)
              ->where('user_id', $event->user->id)
              ->update(['last_used_at' => now()]);
    }
}
