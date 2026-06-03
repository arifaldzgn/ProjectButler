<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpFoundation\Response;

/**
 * IdempotencyMiddleware
 *
 * Prevents duplicate processing when iPhone Shortcuts or mobile clients
 * retry a request (e.g. due to network timeout).
 *
 * Usage:
 *   Client sends header: Idempotency-Key: <uuid-or-hash>
 *   - First request:  processed normally, response cached for TTL
 *   - Repeat request: cached response returned immediately
 *
 * Cache key: "idempotent:{userId}:{idempotencyKey}"
 * TTL: config('butler.shortcut.idempotency_ttl_seconds', 600) — default 10 min
 *
 * If no Idempotency-Key header is sent, the request passes through normally.
 */
class IdempotencyMiddleware
{
    public function handle(Request $request, Closure $next): Response
    {
        $key = $request->header('Idempotency-Key');

        // No key provided — pass through (don't enforce idempotency)
        if (!$key) {
            return $next($request);
        }

        // Validate key length to prevent cache key abuse
        if (strlen($key) > 128) {
            return response()->json([
                'success' => false,
                'message' => 'Idempotency-Key must be 128 characters or fewer.',
            ], 400);
        }

        $userId   = $request->user()?->id ?? 'anonymous';
        $cacheKey = "idempotent:{$userId}:" . hash('sha256', $key);
        $ttl      = config('butler.shortcut.idempotency_ttl_seconds', 600);

        // Return cached response if this key was already processed
        if (Cache::has($cacheKey)) {
            $cached = Cache::get($cacheKey);

            return response()->json(
                $cached['body'],
                $cached['status']
            )->withHeaders([
                'X-Idempotency-Replayed' => 'true',
                'X-Idempotency-Key'      => $key,
            ]);
        }

        // Mark key as "in-flight" to handle concurrent duplicate requests
        $lockKey = "idempotent_lock:{$userId}:" . hash('sha256', $key);
        if (Cache::has($lockKey)) {
            return response()->json([
                'success' => false,
                'message' => 'A request with this Idempotency-Key is currently being processed.',
            ], 409);
        }

        // Acquire lock (expires after 30s to handle crashed workers)
        Cache::put($lockKey, true, 30);

        /** @var Response $response */
        $response = $next($request);

        // Cache the response (only 2xx responses are cached)
        if ($response->getStatusCode() >= 200 && $response->getStatusCode() < 300) {
            $body = json_decode($response->getContent(), true);
            Cache::put($cacheKey, [
                'body'   => $body,
                'status' => $response->getStatusCode(),
            ], $ttl);
        }

        Cache::forget($lockKey);

        return $response->withHeaders([
            'X-Idempotency-Key'      => $key,
            'X-Idempotency-Replayed' => 'false',
        ]);
    }
}
