<?php

namespace App\Providers;

use App\Events\AiResponseGenerated;
use App\Events\DeviceRegistered;
use App\Events\DeviceRevoked;
use App\Events\MessageReceived;
use App\Listeners\LogMessageReceived;
use App\Listeners\RecordAnalyticsEvent;
use App\Listeners\UpdateDeviceLastUsed;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        // ── Rate limiter: 30 req/min per authenticated user ────────────
        RateLimiter::for('shortcut', function (Request $request) {
            return Limit::perMinute(config('butler.shortcut.rate_limit', 30))
                ->by($request->user()?->id ?: $request->ip())
                ->response(function () {
                    return response()->json([
                        'success'     => false,
                        'message'     => 'Too Many Requests. Coba lagi dalam 1 menit.',
                        'retry_after' => 60,
                    ], 429);
                });
        });

        // ── Domain event listeners ─────────────────────────────────────
        // MessageReceived → log conversation turn + update device last_used_at
        Event::listen(MessageReceived::class, LogMessageReceived::class);
        Event::listen(MessageReceived::class, UpdateDeviceLastUsed::class);

        // AiResponseGenerated → increment daily analytics counters
        Event::listen(AiResponseGenerated::class, RecordAnalyticsEvent::class);
    }
}
