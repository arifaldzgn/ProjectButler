<?php

namespace App\Http\Controllers;

use App\Models\AiLog;
use App\Models\User;
use Illuminate\Http\Request;

class AdminController extends Controller
{
    public function index()
    {
        $users = User::withCount(['entries', 'funds'])->orderByDesc('created_at')->paginate(50);
        return view('admin.users.index', compact('users'));
    }

    public function aiLogs(Request $request)
    {
        $query = AiLog::with('user')
            ->orderByDesc('created_at');

        // Filters
        if ($request->filled('call_type')) {
            $query->where('call_type', $request->call_type);
        }
        if ($request->filled('success')) {
            $query->where('was_successful', $request->success === '1');
        }
        if ($request->filled('user_id')) {
            $query->where('user_id', $request->user_id);
        }

        $logs      = $query->paginate(50)->withQueryString();
        $callTypes = AiLog::distinct()->pluck('call_type')->sort()->values();
        $users     = User::orderBy('name')->get(['id', 'name']);

        // Quick stats for the header
        $stats = [
            'total_today'      => AiLog::whereDate('created_at', today())->count(),
            'failures_today'   => AiLog::whereDate('created_at', today())->where('was_successful', false)->count(),
            'avg_latency_ms'   => (int) AiLog::whereDate('created_at', today())->avg('latency_ms'),
            'avg_confidence'   => round((float) AiLog::whereDate('created_at', today())->whereNotNull('confidence_score')->avg('confidence_score'), 2),
        ];

        return view('admin.ai-logs.index', compact('logs', 'callTypes', 'users', 'stats'));
    }

    public function impersonate(Request $request, User $user)
    {
        $admin = $request->dashboard_user;

        session([
            'admin_impersonator_id' => $admin->id,
            'dashboard_user_id' => $user->id,
            'dashboard_expires' => now()->addMinutes(120)->timestamp,
        ]);

        return redirect()->route('dashboard.index')->with('success', "You are now impersonating {$user->name}.");
    }

    public function leaveImpersonate(Request $request)
    {
        $adminId = session('admin_impersonator_id');

        if ($adminId) {
            session([
                'dashboard_user_id' => $adminId,
                'dashboard_expires' => now()->addMinutes(30)->timestamp,
            ]);
            session()->forget('admin_impersonator_id');
            return redirect()->route('admin.users.index')->with('success', 'Left impersonation.');
        }

        return redirect()->route('dashboard.index');
    }
}
