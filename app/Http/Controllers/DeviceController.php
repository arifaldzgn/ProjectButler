<?php

namespace App\Http\Controllers;

use App\Events\DeviceRevoked;
use App\Models\Device;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/**
 * DeviceController
 *
 * Lets authenticated users manage their registered devices:
 *   GET    /api/devices              → list all devices
 *   PATCH  /api/devices/{id}         → rename a device
 *   DELETE /api/devices/{id}         → revoke a device (+ its Sanctum token)
 *   GET    /api/devices/{id}/activity → last_used_at + metadata
 *
 * All actions are scoped to the authenticated user — no cross-user access.
 */
class DeviceController extends Controller
{
    // ── GET /api/devices ──────────────────────────────────────────────

    public function index(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $devices = Device::where('user_id', $user->id)
            ->orderBy('last_used_at', 'desc')
            ->get()
            ->map(fn(Device $d) => $this->formatDevice($d));

        return response()->json([
            'success' => true,
            'data'    => $devices,
        ]);
    }

    // ── PATCH /api/devices/{id} ───────────────────────────────────────

    public function rename(Request $request, int $id): JsonResponse
    {
        $device = $this->findForUser($request->user(), $id);
        if (!$device) {
            return $this->notFound();
        }

        try {
            $validated = $request->validate([
                'name' => ['required', 'string', 'min:1', 'max:100'],
            ]);
        } catch (ValidationException $e) {
            return response()->json(['success' => false, 'errors' => $e->errors()], 422);
        }

        $device->update(['name' => $validated['name']]);

        return response()->json([
            'success' => true,
            'message' => 'Device renamed.',
            'data'    => $this->formatDevice($device->fresh()),
        ]);
    }

    // ── DELETE /api/devices/{id} ──────────────────────────────────────

    public function revoke(Request $request, int $id): JsonResponse
    {
        /** @var User $user */
        $user   = $request->user();
        $device = $this->findForUser($user, $id);

        if (!$device) {
            return $this->notFound();
        }

        // Prevent revoking the currently authenticated device (would 401 the response)
        $currentTokenId = $user->currentAccessToken()?->id;
        if ($device->token_id && $device->token_id == $currentTokenId) {
            return response()->json([
                'success' => false,
                'message' => 'Cannot revoke the currently active device token. Use /api/shortcut/token/{id} from another device.',
            ], 409);
        }

        $device->revoke();

        event(new DeviceRevoked($user, $device));

        return response()->json([
            'success' => true,
            'message' => "Device \"{$device->name}\" revoked. Its token is no longer valid.",
        ]);
    }

    // ── GET /api/devices/{id}/activity ────────────────────────────────

    public function activity(Request $request, int $id): JsonResponse
    {
        $device = $this->findForUser($request->user(), $id);
        if (!$device) {
            return $this->notFound();
        }

        return response()->json([
            'success' => true,
            'data'    => [
                'id'           => $device->id,
                'name'         => $device->name,
                'platform'     => $device->platform,
                'is_active'    => $device->is_active,
                'last_used_at' => $device->last_used_at?->toIso8601String(),
                'created_at'   => $device->created_at->toIso8601String(),
                'metadata'     => $device->metadata,
            ],
        ]);
    }

    // ── Helpers ───────────────────────────────────────────────────────

    private function findForUser(User $user, int $deviceId): ?Device
    {
        return Device::where('id', $deviceId)
                     ->where('user_id', $user->id)
                     ->first();
    }

    private function formatDevice(Device $device): array
    {
        return [
            'id'           => $device->id,
            'name'         => $device->name,
            'platform'     => $device->platform,
            'is_active'    => $device->is_active,
            'last_used_at' => $device->last_used_at?->toIso8601String(),
            'created_at'   => $device->created_at->toIso8601String(),
        ];
    }

    private function notFound(): JsonResponse
    {
        return response()->json(['success' => false, 'message' => 'Device not found.'], 404);
    }
}
