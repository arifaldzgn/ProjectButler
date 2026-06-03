<?php

namespace App\Http\Controllers;

use App\Models\Device;
use App\Models\User;
use App\Services\ShortcutMessageService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

/**
 * ShortcutMessageController
 *
 * Public-facing API consumed by iPhone Shortcuts, Android HTTP clients,
 * web dashboards, and any future client. The Telegram Bot Token is NEVER
 * read or returned here — all bot interaction happens server-side in the queue.
 *
 * Routes (see routes/api.php):
 *   POST   /api/shortcut/message          → handle()
 *   GET    /api/shortcut/status/{id}      → status()
 *   POST   /api/shortcut/token            → issueToken()  (admin-only)
 *   DELETE /api/shortcut/token/{tokenId}  → revokeToken()
 */
class ShortcutMessageController extends Controller
{
    public function __construct(
        private readonly ShortcutMessageService $service
    ) {}

    // ── POST /api/shortcut/message ─────────────────────────────────────────

    /**
     * Accept a message from a Shortcut client, persist it, and queue it for
     * the existing AI/Telegram pipeline.
     */
    public function handle(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'message'  => ['required', 'string', 'min:1', 'max:2000'],
                'source'   => ['nullable', 'string', 'in:iphone_shortcut,android,web,desktop,raycast,test'],
                // Optional intent hint — skips AI parse if provided, falls back gracefully
                'intent'   => ['nullable', 'string', 'in:expense,income,task,note,journal,health,reminder,general_chat'],
                'metadata' => ['nullable', 'array'],
                'metadata.shortcut_version' => ['nullable', 'string', 'max:20'],
                'metadata.device'           => ['nullable', 'string', 'max:100'],
            ]);
        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
                'errors'  => $e->errors(),
            ], 422);
        }

        /** @var User $user */
        $user = $request->user();

        // Resolve which registered Device made this request (if any)
        $tokenId  = $user->currentAccessToken()?->id;
        $deviceId = $tokenId ? Device::forToken($tokenId)?->id : null;

        $shortcutMessage = $this->service->process(
            user:     $user,
            message:  $validated['message'],
            source:   $validated['source'] ?? 'iphone_shortcut',
            metadata: $validated['metadata'] ?? [],
            intentHint: $validated['intent'] ?? null,
            deviceId:   $deviceId,
        );

        $statusText = match ($shortcutMessage->status) {
            'processed' => 'Berhasil diproses.',
            'failed'    => 'Terjadi kesalahan saat memproses pesanmu.',
            default     => 'Pesan diterima dan sedang diproses.',
        };

        return response()->json([
            'success' => true,
            'message' => $statusText,
            'data'    => [
                'shortcut_message_id' => $shortcutMessage->id,
                'status'              => $shortcutMessage->status,
                'response'            => $shortcutMessage->response,
            ],
        ]);
    }

    // ── GET /api/shortcut/status/{id} ─────────────────────────────────────

    /**
     * Poll the status of a previously submitted message.
     * Useful for Shortcuts running in async mode that check back after a delay.
     */
    public function status(Request $request, int $id): JsonResponse
    {
        /** @var User $user */
        $user    = $request->user();
        $message = $this->service->find($id, $user);

        if (!$message) {
            return response()->json([
                'success' => false,
                'message' => 'Message not found.',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data'    => [
                'shortcut_message_id' => $message->id,
                'status'              => $message->status,
                'response'            => $message->response,
                'submitted_at'        => $message->created_at->toIso8601String(),
                'processed_at'        => $message->processed_at?->toIso8601String(),
            ],
        ]);
    }

    // ── POST /api/shortcut/token ───────────────────────────────────────────

    /**
     * Issue a Sanctum API token for a user.
     * Protected by the SHORTCUT_ADMIN_SECRET header — for personal/admin use only.
     *
     * For multi-user production, replace this with a proper OAuth/login flow.
     */
    public function issueToken(Request $request): JsonResponse
    {
        // Verify admin secret from env — never hardcode
        $secret = $request->header('X-Admin-Secret');
        if ($secret !== config('butler.shortcut.admin_secret')) {
            return response()->json(['success' => false, 'message' => 'Forbidden.'], 403);
        }

        $validated = $request->validate([
            'user_id'    => ['required', 'integer', 'exists:users,id'],
            'token_name' => ['required', 'string', 'max:100'],
        ]);

        $user  = User::findOrFail($validated['user_id']);
        $token = $user->createToken(
            $validated['token_name'],
            ['shortcut:send', 'shortcut:read'] // Sanctum abilities (scopes)
        );

        Log::info('Shortcut API token issued', [
            'user_id'    => $user->id,
            'token_name' => $validated['token_name'],
        ]);

        return response()->json([
            'success'    => true,
            'token'      => $token->plainTextToken, // Show ONCE — store securely
            'token_id'   => $token->accessToken->id,
            'user_id'    => $user->id,
            'user_name'  => $user->name,
            'message'    => 'Store this token securely. It will not be shown again.',
        ]);
    }

    // ── DELETE /api/shortcut/token/{tokenId} ──────────────────────────────

    /**
     * Revoke a specific token (e.g., when device is lost or replaced).
     * The authenticated user can only revoke their own tokens.
     */
    public function revokeToken(Request $request, int $tokenId): JsonResponse
    {
        /** @var User $user */
        $user    = $request->user();
        $deleted = $user->tokens()->where('id', $tokenId)->delete();

        if (!$deleted) {
            return response()->json([
                'success' => false,
                'message' => 'Token not found or not yours.',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'message' => 'Token revoked successfully.',
        ]);
    }
}
