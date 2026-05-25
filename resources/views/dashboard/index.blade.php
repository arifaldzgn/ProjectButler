@extends('layouts.dashboard')
@section('title', 'Dashboard — Butler')
@section('content')

<div class="animate-in">
    <h2>Halo, {{ $user->name }} 👋</h2>
    <p class="page-desc">Ini ringkasan hari ini.</p>
</div>

<div class="stat-grid animate-in" style="animation-delay:.05s">
    <div class="stat-card">
        <div class="stat-label">Pengeluaran hari ini</div>
        <div class="stat-value">Rp {{ number_format($todaySpend, 0, ',', '.') }}</div>
        @if($user->daily_budget_idr)
            @php $rem = $user->daily_budget_idr - $todaySpend; @endphp
            <div class="stat-sub" style="color: {{ $rem >= 0 ? 'var(--success)' : 'var(--err)' }}">
                {{ $rem >= 0 ? 'Sisa Rp ' . number_format($rem, 0, ',', '.') : 'Melebihi Rp ' . number_format(abs($rem), 0, ',', '.') }}
            </div>
        @endif
    </div>
    @if($user->isCalorieMode())
    <div class="stat-card">
        <div class="stat-label">Kalori hari ini</div>
        <div class="stat-value">{{ number_format($todayCalories, 0, ',', '.') }} <span style="font-size:14px;font-weight:400;color:var(--text-muted)">kcal</span></div>
        @if($user->daily_calorie_goal)
            @php $calRem = $user->daily_calorie_goal - $todayCalories; @endphp
            <div class="stat-sub" style="color: {{ $calRem >= 0 ? 'var(--success)' : 'var(--warning)' }}">
                {{ $calRem >= 0 ? 'Sisa ' . number_format($calRem) . ' kcal' : 'Lewat ' . number_format(abs($calRem)) . ' kcal' }}
            </div>
        @endif
    </div>
    @else
    <div class="stat-card">
        <div class="stat-label">Budget bulanan</div>
        <div class="stat-value">
            @if($user->monthly_budget_idr)
                Rp {{ number_format($user->monthly_budget_idr, 0, ',', '.') }}
            @else
                <span style="color:var(--text-dim);font-size:16px">Belum diset</span>
            @endif
        </div>
    </div>
    @endif
</div>

@if($accounts->isNotEmpty())
<div class="animate-in" style="animation-delay:.1s;margin-bottom:24px">
    <div style="font-size:11px;font-weight:600;letter-spacing:0.08em;text-transform:uppercase;color:var(--text-dim);margin-bottom:10px">Akun</div>
    <div class="table-wrap">
        @foreach($accounts as $account)
        <div class="account-row">
            <div>
                <div class="account-name">
                    {{ $account->name }}
                    @if($account->is_default_spending)<span class="default-badge">Utama</span>@endif
                </div>
                <div class="account-type">{{ ucfirst($account->type ?? 'Akun') }}</div>
            </div>
            <div class="account-balance">Rp {{ number_format($account->current_balance, 0, ',', '.') }}</div>
        </div>
        @endforeach
    </div>
</div>
@endif

<a href="{{ route('dashboard.history') }}"
   class="animate-in"
   style="animation-delay:.15s;display:flex;align-items:center;justify-content:space-between;padding:16px;background:var(--bg-card);border:1px solid var(--border);border-radius:var(--radius);text-decoration:none;color:var(--text)">
    <span style="font-weight:500">Lihat semua riwayat</span>
    <span style="color:var(--text-dim)">→</span>
</a>

@endsection
