<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;

class AdminController extends Controller
{
    public function index()
    {
        $users = User::withCount(['entries', 'funds'])->orderByDesc('created_at')->paginate(50);
        return view('admin.users.index', compact('users'));
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
