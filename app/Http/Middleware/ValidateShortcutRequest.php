<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Validates that every Shortcut API request contains a well-formed payload
 * and that the authenticated user has completed onboarding.
 * Runs after auth:sanctum so $request->user() is already resolved.
 */
class ValidateShortcutRequest
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        // Ensure the user exists (auth:sanctum should have caught this, but belt + suspenders)
        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthenticated.',
            ], 401);
        }

        // Block users who haven't completed onboarding — they have no AI context yet
        if ($user->isOnboardingGated()) {
            return response()->json([
                'success' => false,
                'message' => 'Setup belum selesai. Buka Telegram dan selesaikan onboarding dulu.',
                'hint'    => 'Kirim /start ke Bot Telegram untuk melanjutkan setup.',
            ], 403);
        }

        // Validate Content-Type for POST requests
        if ($request->isMethod('POST') && !$request->isJson()) {
            return response()->json([
                'success' => false,
                'message' => 'Content-Type must be application/json.',
            ], 415);
        }

        return $next($request);
    }
}
