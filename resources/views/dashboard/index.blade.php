@extends('layouts.dashboard')
@section('title', 'Dashboard — Butler')
@section('content')

<div class="animate-in">
    <h2>Halo, {{ $user->name }} 👋</h2>
    <p class="page-desc">Ini ringkasan hari ini.</p>
</div>

<div class="stat-grid animate-in" style="animation-delay:.05s; margin-top: 24px;">
    <!-- Today Stats -->
    <div class="stat-card">
        <div class="stat-label">Pengeluaran Hari Ini</div>
        <div class="stat-value" style="color:var(--accent)">Rp {{ number_format($todaySpend, 0, ',', '.') }}</div>
        @if($user->daily_budget_idr)
            @php $rem = $user->daily_budget_idr - $todaySpend; @endphp
            <div class="stat-sub" style="color: {{ $rem >= 0 ? 'var(--success)' : 'var(--err)' }}">
                {{ $rem >= 0 ? 'Sisa Rp ' . number_format($rem, 0, ',', '.') : 'Over Rp ' . number_format(abs($rem), 0, ',', '.') }}
            </div>
        @endif
    </div>

    @if($user->isCalorieMode())
    <div class="stat-card">
        <div class="stat-label">Kalori Hari Ini</div>
        <div class="stat-value" style="color:#f59e0b">{{ number_format($todayCalories, 0, ',', '.') }} <span style="font-size:14px;font-weight:400;color:var(--text-muted)">kcal</span></div>
        @if($user->daily_calorie_goal)
            @php $calRem = $user->daily_calorie_goal - $todayCalories; @endphp
            <div class="stat-sub" style="color: {{ $calRem >= 0 ? 'var(--success)' : 'var(--warning)' }}">
                {{ $calRem >= 0 ? 'Sisa ' . number_format($calRem) . ' kcal' : 'Lewat ' . number_format(abs($calRem)) . ' kcal' }}
            </div>
        @endif
    </div>
    @endif

    <!-- Monthly Stats -->
    <div class="stat-card" style="background:rgba(239,68,68,0.05);border-color:rgba(239,68,68,0.1)">
        <div class="stat-label">Total Spending (Bulan Ini)</div>
        <div class="stat-value" style="color:#fca5a5">Rp {{ number_format($monthlySpend, 0, ',', '.') }}</div>
        @if($user->monthly_budget_idr)
            @php $mRem = $user->monthly_budget_idr - $monthlySpend; @endphp
            <div class="stat-sub" style="color: {{ $mRem >= 0 ? 'var(--success)' : 'var(--err)' }}">
                {{ $mRem >= 0 ? 'Sisa Rp ' . number_format($mRem, 0, ',', '.') : 'Over Rp ' . number_format(abs($mRem), 0, ',', '.') }}
            </div>
        @endif
    </div>
    
    <div class="stat-card" style="background:rgba(16,185,129,0.05);border-color:rgba(16,185,129,0.1)">
        <div class="stat-label">Total Income (Bulan Ini)</div>
        <div class="stat-value" style="color:#6ee7b7">Rp {{ number_format($monthlyIncome, 0, ',', '.') }}</div>
    </div>
</div>

@if($accounts->isNotEmpty())
<div class="animate-in" style="animation-delay:.1s">
    <div class="section-title">Wallets</div>
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

@if(isset($sinkingFunds) && $sinkingFunds->isNotEmpty())
<div class="animate-in" style="animation-delay:.15s">
    <div class="section-title">Goals & Sinking Funds</div>
    <div style="display:flex;flex-direction:column;">
        @foreach($sinkingFunds as $fund)
        @php
            $target = $fund->target_amount ?: 1; // prevent div by zero
            $pct = min(100, round(($fund->current_balance / $target) * 100));
        @endphp
        <div class="fund-card" x-data="{ open: false }" @click="open = !open" style="cursor:pointer; user-select:none;">
            <div class="fund-header">
                <div class="fund-name">📁 {{ $fund->name }}</div>
                <div class="fund-balance">Rp {{ number_format($fund->current_balance, 0, ',', '.') }}</div>
            </div>
            <div class="fund-target">Target: Rp {{ number_format($fund->target_amount, 0, ',', '.') }} ({{ $pct }}%)</div>
            <div class="progress-track">
                <div class="progress-fill" style="width: {{ $pct }}%"></div>
            </div>
            
            <div x-show="open" x-transition x-cloak style="margin-top: 12px; padding-top: 12px; border-top: 1px solid var(--border);">
                <div style="font-size: 11px; color: var(--text-muted); text-transform: uppercase; letter-spacing: 0.05em; margin-bottom: 8px;">Rincian Sumber Dana (Deposit)</div>
                @if(empty($fund->breakdown))
                    <div style="font-size: 12px; color: var(--text-dim);">Belum ada setoran tercatat.</div>
                @else
                    @foreach($fund->breakdown as $source => $amount)
                        <div style="display:flex; justify-content:space-between; font-size:13px; margin-bottom:4px;">
                            <span style="color:var(--text-muted)">{{ $source }}</span>
                            <span style="font-weight:500">Rp {{ number_format($amount, 0, ',', '.') }}</span>
                        </div>
                    @endforeach
                @endif
            </div>
        </div>
        @endforeach
    </div>
</div>
@endif

@if(isset($recentActivities) && $recentActivities->isNotEmpty())
<div class="animate-in" style="animation-delay:.2s">
    <div class="section-title">Recent Activities</div>
    <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th>Aktivitas</th>
                    <th style="text-align:right">Nominal</th>
                </tr>
            </thead>
            <tbody>
                @foreach($recentActivities as $entry)
                <tr>
                    <td>
                        <div style="font-weight:500;color:var(--text)">{{ $entry->note ?: ucfirst($entry->category) }}</div>
                        <div style="font-size:12px;color:var(--text-muted);margin-top:2px">{{ $entry->entry_time->format('d M H:i') }}</div>
                    </td>
                    <td style="text-align:right">
                        @if($entry->type === 'expense')
                            <span class="amount-expense">-Rp {{ number_format($entry->amount, 0, ',', '.') }}</span>
                        @elseif($entry->type === 'income' || $entry->type === 'saving')
                            <span class="amount-income">+Rp {{ number_format($entry->amount, 0, ',', '.') }}</span>
                        @else
                            <span style="font-weight:600">Rp {{ number_format($entry->amount, 0, ',', '.') }}</span>
                        @endif
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</div>
@endif

<a href="{{ route('dashboard.history') }}"
   class="animate-in"
   style="animation-delay:.25s;display:flex;align-items:center;justify-content:space-between;padding:16px;background:var(--bg-card);border:1px solid var(--border);border-radius:var(--radius);text-decoration:none;color:var(--text);margin-top:24px;">
    <span style="font-weight:500">Lihat semua riwayat</span>
    <span style="color:var(--text-dim)">→</span>
</a>

@endsection
