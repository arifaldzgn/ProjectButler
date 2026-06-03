<?php

namespace App\Services;

use App\Models\Device;
use App\Models\PairingCode;
use App\Models\User;
use Illuminate\Support\Facades\Log;

/**
 * Handles device pairing logic for internal callers (Telegram bot, etc.).
 *
 * The HTTP layer (PairingController) is not changed — it is still the entry
 * point for the iPhone Shortcut. This service is used exclusively by
 * server-side callers (MessageRouter) that are already authenticated.
 */
class DevicePairingService
{
    /**
     * Generate a fresh pairing code for a user, expiring any previous unused codes.
     */
    public function generateCode(
        User   $user,
        string $deviceName = 'iPhone',
        string $platform   = 'ios'
    ): PairingCode {
        PairingCode::where('user_id', $user->id)
            ->whereNull('claimed_at')
            ->where('expires_at', '>', now())
            ->update(['expires_at' => now()]);

        $ttl = config('butler.shortcut.pairing_ttl_minutes', 15);

        return PairingCode::create([
            'user_id'     => $user->id,
            'code'        => PairingCode::generateCode(),
            'device_name' => $deviceName,
            'platform'    => $platform,
            'expires_at'  => now()->addMinutes($ttl),
        ]);
    }

    /**
     * Return all currently active (non-revoked) devices for a user.
     */
    public function listActiveDevices(User $user): \Illuminate\Database\Eloquent\Collection
    {
        return $user->activeDevices()->orderByDesc('last_used_at')->get();
    }

    /**
     * Revoke a device belonging to this user. Returns false if not found.
     */
    public function revokeDevice(User $user, int $deviceId): bool
    {
        $device = $user->activeDevices()->find($deviceId);

        if (!$device) {
            return false;
        }

        $device->revoke();

        Log::info('Device revoked via Telegram', [
            'user_id'   => $user->id,
            'device_id' => $deviceId,
        ]);

        return true;
    }

    /**
     * Build the Telegram message text shown after /pair_iphone.
     */
    public function buildPairingMessage(PairingCode $code): string
    {
        $expiresIn = config('butler.shortcut.pairing_ttl_minutes', 15);

        return "📱 *Pasang iPhone Shortcut*\n\n"
            . "Kode pairing kamu:\n\n"
            . "`{$code->code}`\n\n"
            . "Langkah-langkah:\n"
            . "1. Tap *Instal Shortcut* di bawah\n"
            . "2. Buka shortcut *Butler* yang baru terinstal\n"
            . "3. Masukkan kode `{$code->code}` saat diminta\n"
            . "4. Selesai — coba bilang: _\"Hey Siri, Butler\"_\n\n"
            . "_Kode berlaku selama {$expiresIn} menit._";
    }

    /**
     * Build the inline keyboard for the pairing message.
     */
    public function buildPairingButtons(PairingCode $code): array
    {
        $installUrl = config('butler.shortcut.install_url');

        $buttons = [];

        if ($installUrl) {
            $buttons[] = ['text' => '📲 Instal Shortcut', 'url' => $installUrl];
        }

        $buttons[] = [
            'text'          => '❌ Batalkan',
            'callback_data' => "pair_device:cancel:{$code->id}",
        ];

        return $buttons;
    }

    /**
     * Build the devices list message.
     */
    public function buildDevicesMessage(User $user): string
    {
        $devices = $this->listActiveDevices($user);

        if ($devices->isEmpty()) {
            return "📱 *Perangkat Terpasang*\n\n"
                . "_Belum ada perangkat terpasang._\n\n"
                . "Ketik /pair\\_iphone untuk pasang iPhone Shortcut.";
        }

        $msg = "📱 *Perangkat Terpasang*\n\n";

        foreach ($devices as $device) {
            $platformEmoji = match ($device->platform) {
                'ios'     => '📱',
                'android' => '🤖',
                'desktop' => '💻',
                'web'     => '🌐',
                'raycast' => '⚡',
                default   => '📟',
            };

            $lastUsed = $device->last_used_at
                ? $device->last_used_at->diffForHumans()
                : 'belum pernah';

            $msg .= "{$platformEmoji} *{$device->name}*\n";
            $msg .= "   Terakhir: {$lastUsed}\n\n";
        }

        $msg .= "_Tap tombol di bawah untuk menghapus perangkat._";

        return $msg;
    }

    /**
     * Build the per-device revoke keyboard for the devices list message.
     * Each device gets its own "Hapus" button in a separate row.
     */
    public function buildDevicesButtons(User $user): array
    {
        $devices = $this->listActiveDevices($user);
        $buttons = [];

        foreach ($devices as $device) {
            $buttons[] = [
                'text'          => "Hapus: {$device->name}",
                'callback_data' => "pair_device:revoke:{$device->id}",
            ];
        }

        $buttons[] = [
            'text'          => '+ Hubungkan Perangkat Baru',
            'callback_data' => 'pair_device:new',
        ];

        return $buttons;
    }
}
