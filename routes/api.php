<?php

use App\Http\Controllers\DeviceController;
use App\Http\Controllers\PairingController;
use App\Http\Controllers\ShortcutMessageController;
use App\Http\Controllers\TelegramWebhookController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

// ── Standard user endpoint ────────────────────────────────────────────────
Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

// ── Telegram webhook (server-to-server, no auth needed) ───────────────────
Route::post('/telegram/webhook', TelegramWebhookController::class);

// ── Self-service pairing (unauthenticated — code IS the credential) ───────
Route::post('/pair/claim', [PairingController::class, 'claim'])
    ->name('pair.claim');

// ── Admin-secret protected (token + pairing issuance) ────────────────────
Route::post('/shortcut/token', [ShortcutMessageController::class, 'issueToken']);
Route::post('/pair/request', [PairingController::class, 'request'])
    ->name('pair.request');

// ── Authenticated API routes ──────────────────────────────────────────────
// Middleware stack: Sanctum → onboarding gate → rate limit → idempotency
Route::middleware([
    'auth:sanctum',
    \App\Http\Middleware\ValidateShortcutRequest::class,
    'throttle:shortcut',
    \App\Http\Middleware\IdempotencyMiddleware::class,
])->group(function () {

    // ── Shortcut messages ─────────────────────────────────────────────
    Route::prefix('shortcut')->group(function () {
        Route::post('/message', [ShortcutMessageController::class, 'handle'])
            ->name('shortcut.message');
        Route::get('/status/{id}', [ShortcutMessageController::class, 'status'])
            ->name('shortcut.status')
            ->where('id', '[0-9]+');
        Route::delete('/token/{tokenId}', [ShortcutMessageController::class, 'revokeToken'])
            ->name('shortcut.token.revoke')
            ->where('tokenId', '[0-9]+');
    });

    // ── Device management ─────────────────────────────────────────────
    Route::prefix('devices')->group(function () {
        Route::get('/', [DeviceController::class, 'index'])
            ->name('devices.index');
        Route::patch('/{id}', [DeviceController::class, 'rename'])
            ->name('devices.rename')
            ->where('id', '[0-9]+');
        Route::delete('/{id}', [DeviceController::class, 'revoke'])
            ->name('devices.revoke')
            ->where('id', '[0-9]+');
        Route::get('/{id}/activity', [DeviceController::class, 'activity'])
            ->name('devices.activity')
            ->where('id', '[0-9]+');
    });
});