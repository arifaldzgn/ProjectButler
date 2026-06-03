<?php

namespace App\Http\Controllers;

use App\Events\DeviceRegistered;
use App\Models\Device;
use App\Models\PairingCode;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/**
 * PairingController
 *
 * Self-service device onboarding — no admin intervention required.
 *
 * Flow:
 *   1. User authenticates (via Telegram bot command, web login, etc.)
 *   2. POST /api/pair/request  → server creates PairingCode, returns 6-char code
 *   3. User enters code into iPhone Shortcut (or scans QR)
 *   4. POST /api/pair/claim    → Shortcut sends code, server creates Device + Token
 *   5. Shortcut stores the returned token in its header
 *
 * Auth on /pair/request uses the existing admin secret (for now).
 * Future: Replace with full web login flow.
 */
class PairingController extends Controller
{
    // ── POST /api/pair/request ────────────────────────────────────────

    /**
     * Generate a one-time pairing code for a user.
     * Protected by the X-Admin-Secret header (same as token issuance).
     */
    public function request(Request $request): JsonResponse
    {
        // Verify admin secret
        $secret = $request->header('X-Admin-Secret');
        if ($secret !== config('butler.shortcut.admin_secret')) {
            return response()->json(['success' => false, 'message' => 'Forbidden.'], 403);
        }

        try {
            $validated = $request->validate([
                'user_id'     => ['required', 'integer', 'exists:users,id'],
                'device_name' => ['nullable', 'string', 'max:100'],
                'platform'    => ['nullable', 'string', 'in:ios,android,desktop,web,raycast'],
            ]);
        } catch (ValidationException $e) {
            return response()->json(['success' => false, 'errors' => $e->errors()], 422);
        }

        $user = User::findOrFail($validated['user_id']);

        // Expire any unused previous codes for this user
        PairingCode::where('user_id', $user->id)
            ->whereNull('claimed_at')
            ->where('expires_at', '>', now())
            ->update(['expires_at' => now()]);

        $ttlMinutes = config('butler.shortcut.pairing_ttl_minutes', 15);

        $code = PairingCode::create([
            'user_id'     => $user->id,
            'code'        => PairingCode::generateCode(),
            'device_name' => $validated['device_name'] ?? 'My Device',
            'platform'    => $validated['platform'] ?? 'ios',
            'expires_at'  => now()->addMinutes($ttlMinutes),
        ]);

        return response()->json([
            'success'     => true,
            'code'        => $code->code,
            'expires_at'  => $code->expires_at->toIso8601String(),
            'expires_in_minutes' => $ttlMinutes,
            'claim_url'   => url("/api/pair/claim"),
            'instructions' => [
                'step1' => "Enter code {$code->code} in your iPhone Shortcut",
                'step2' => "POST {$code->code} to " . url('/api/pair/claim'),
                'step3' => 'Store the returned token in your Shortcut headers',
            ],
        ]);
    }

    // ── POST /api/pair/claim ──────────────────────────────────────────

    /**
     * Claim a pairing code. Called by the iPhone Shortcut.
     * Returns a Sanctum token the Shortcut stores and uses for future requests.
     * This endpoint is intentionally unauthenticated — the code IS the credential.
     */
    public function claim(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'code'        => ['required', 'string', 'size:6'],
                'device_name' => ['nullable', 'string', 'max:100'],
                'metadata'    => ['nullable', 'array'],
                'metadata.os_version'   => ['nullable', 'string', 'max:20'],
                'metadata.device_model' => ['nullable', 'string', 'max:100'],
                'metadata.app_version'  => ['nullable', 'string', 'max:20'],
            ]);
        } catch (ValidationException $e) {
            return response()->json(['success' => false, 'errors' => $e->errors()], 422);
        }

        $pairingCode = PairingCode::where('code', strtoupper($validated['code']))->first();

        if (!$pairingCode) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid pairing code.',
            ], 404);
        }

        if (!$pairingCode->isUsable()) {
            $reason = $pairingCode->isClaimed() ? 'already claimed' : 'expired';
            return response()->json([
                'success' => false,
                'message' => "Pairing code is {$reason}. Request a new one.",
            ], 410);
        }

        $user       = $pairingCode->user;
        $deviceName = $validated['device_name'] ?? $pairingCode->device_name ?? 'My Device';
        $metadata   = array_merge($validated['metadata'] ?? [], [
            'paired_at' => now()->toIso8601String(),
            'ip'        => $request->ip(),
        ]);

        // Create the Sanctum token (scoped to shortcut abilities)
        $tokenResult = $user->createToken(
            $deviceName,
            ['shortcut:send', 'shortcut:read']
        );

        // Create the Device record
        $device = Device::create([
            'user_id'      => $user->id,
            'name'         => $deviceName,
            'platform'     => $pairingCode->platform,
            'token_id'     => $tokenResult->accessToken->id,
            'is_active'    => true,
            'last_used_at' => now(),
            'metadata'     => $metadata,
        ]);

        // Mark the pairing code as claimed
        $pairingCode->update([
            'claimed_at' => now(),
            'device_id'  => $device->id,
        ]);

        event(new DeviceRegistered($user, $device));

        return response()->json([
            'success'    => true,
            'token'      => $tokenResult->plainTextToken, // Shown ONCE — Shortcut must store this
            'device_id'  => $device->id,
            'device_name'=> $device->name,
            'user_id'    => $user->id,
            'message'    => 'Device paired successfully. Store this token — it will not be shown again.',
        ]);
    }
}
