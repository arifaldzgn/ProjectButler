<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class DashboardSession
{
    public function handle(Request $request, Closure $next): Response
    {
        $userId  = session('dashboard_user_id');
        $expires = session('dashboard_expires');

        if (!$userId || !$expires || now()->timestamp > $expires) {
            // Session expired — show message to re-request link from Telegram
            return response()->view('dashboard.expired', [], 403);
        }

        // Sliding session — refresh expiry on every request
        session(['dashboard_expires' => now()->addMinutes(30)->timestamp]);

        // Make user available to all dashboard controllers
        $request->merge(['dashboard_user' => User::findOrFail($userId)]);

        return $next($request);
    }
}
