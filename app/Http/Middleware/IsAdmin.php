<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class IsAdmin
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->dashboard_user;

        if (!$user || !$user->isAdmin()) {
            return redirect()->route('dashboard.index')->with('error', 'Unauthorized access.');
        }

        return $next($request);
    }
}
