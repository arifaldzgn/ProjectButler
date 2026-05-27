@extends('layouts.dashboard')
@section('title', 'Admin Panel — Butler')
@section('content')

<div class="page-header animate-in">
    <div>
        <h2>Admin Panel 🛠️</h2>
        <p>Manage users, monitor AI performance, and review usage patterns.</p>
    </div>
</div>

@include('admin.partials.nav')

@if(session('success'))
    <div style="background: rgba(34,197,94,0.15); color: #86efac; padding: 12px; border-radius: 8px; margin-bottom: 20px;">
        {{ session('success') }}
    </div>
@endif

<div class="animate-in" style="animation-delay:.1s">
    <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th>User</th>
                    <th>Status</th>
                    <th style="text-align:right">Action</th>
                </tr>
            </thead>
            <tbody>
                @foreach($users as $u)
                <tr>
                    <td>
                        <div style="font-weight:500;color:var(--text)">{{ $u->name }}</div>
                        <div style="font-size:12px;color:var(--text-muted);margin-top:2px">{{ '@' . ($u->telegram_username ?? 'Unknown') }}</div>
                    </td>
                    <td>
                        @if($u->isOnboardingComplete())
                            <span class="badge badge-saving">Onboarded</span>
                        @else
                            <span class="badge badge-expense">Pending</span>
                        @endif
                        <div style="font-size:12px;color:var(--text-muted);margin-top:4px">{{ $u->entries_count }} trx, {{ $u->funds_count }} funds</div>
                    </td>
                    <td style="text-align:right">
                        @if($u->id !== request()->dashboard_user->id)
                            <form action="{{ route('admin.impersonate', $u->id) }}" method="POST" style="margin:0;">
                                @csrf
                                <button type="submit" style="background:var(--bg); border:1px solid var(--border); color:var(--text); padding:6px 12px; border-radius:6px; cursor:pointer; font-size:12px; transition:border-color 0.2s;">
                                    Access Dashboard
                                </button>
                            </form>
                        @else
                            <span style="font-size:12px; color:var(--text-dim)">You</span>
                        @endif
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</div>

<div class="pagination" style="margin-top: 24px;">
    {{ $users->links('pagination::bootstrap-4') }}
</div>

@endsection
