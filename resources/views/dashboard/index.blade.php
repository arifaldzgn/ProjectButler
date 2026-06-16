@extends('layouts.dashboard')
@section('title', 'Beranda — Butler')

@section('content')

{{-- ── Header ────────────────────────────────────────────────── --}}
@php
    $hour = now($user->timezone ?? 'Asia/Jakarta')->hour;
    $greet = $hour < 11 ? 'Selamat pagi' : ($hour < 15 ? 'Selamat siang' : ($hour < 19 ? 'Selamat sore' : 'Selamat malam'));
    $waveIcon = $hour < 11 ? 'fa-sun' : ($hour < 19 ? 'fa-hand-wave' : 'fa-moon');
@endphp
<div class="page-header animate-in" style="display:flex;align-items:flex-start;justify-content:space-between;gap:16px;flex-wrap:wrap">
    <div>
        <h2>{{ $greet }}, {{ $user->name }} <i class="fas {{ $waveIcon }}" style="font-size:20px"></i></h2>
        <p>Ringkasan keuangan dan aktivitas Butler-mu hari ini.</p>
    </div>
    <div style="display:flex;gap:8px;align-items:center;flex-shrink:0">
        <div style="padding:8px 14px;background:var(--bg-card);border:1px solid var(--border);border-radius:var(--radius-sm);font-size:12px;font-weight:600;color:var(--text-secondary);box-shadow:var(--card-shadow)">
            <i class="fas fa-calendar-days" style="margin-right:5px"></i>{{ today()->translatedFormat('d M Y') }}
        </div>
    </div>
</div>

{{-- ── Total Balance — Hero card ───────────────────────────────── --}}
<div class="card animate-in net-worth-card" style="animation-delay:.02s;margin-bottom:14px;position:relative;overflow:hidden">

    {{-- Decorative gradient blob --}}
    <div aria-hidden="true" style="
        position:absolute;top:-60px;right:-60px;width:240px;height:240px;
        background:radial-gradient(circle, rgba(139,92,246,0.18), transparent 70%);
        pointer-events:none;
    "></div>

    <div style="display:flex; justify-content:space-between; align-items:flex-start; flex-wrap:wrap; gap:20px; position:relative">

        {{-- Main total --}}
        <div style="min-width:0;flex:1">
            <div style="display:flex;align-items:center;gap:10px;margin-bottom:14px">
                <div style="
                    width:36px;height:36px;border-radius:10px;
                    background:var(--grad-purple);
                    display:inline-flex;align-items:center;justify-content:center;
                    box-shadow:0 6px 14px -4px rgba(124,58,237,.45);
                    color:#fff;font-size:15px;
                "><i class="fas fa-briefcase"></i></div>
                <div style="font-size:13px;font-weight:600;color:var(--text-secondary);letter-spacing:-.01em">
                    Total Kekayaan
                </div>
            </div>
            <div style="font-size:34px;font-weight:800;color:var(--text-primary);line-height:1;letter-spacing:-.03em">
                Rp {{ number_format($totalBalance, 0, ',', '.') }}
            </div>
            <div style="font-size:12px;color:var(--text-muted);margin-top:8px;display:flex;align-items:center;gap:6px">
                <span class="pill pill-success" style="padding:2px 8px;font-size:11px"><i class="fas fa-check" style="font-size:9px"></i> aktif</span>
                <span>{{ $accounts->count() }} akun belanja tersinkron</span>
            </div>
        </div>

        {{-- Breakdown columns --}}
        <div style="display:flex;gap:24px;flex-wrap:wrap">
            @foreach($accounts as $acc)
            <div>
                <div style="font-size:11px;color:var(--text-muted);margin-bottom:6px;text-transform:uppercase;letter-spacing:.06em;font-weight:600">
                    {{ $acc->name }}
                    @if($acc->is_default_spending) <i class="fas fa-star" style="color:var(--accent);font-size:10px"></i> @endif
                </div>
                <div style="font-size:18px;font-weight:700;color:var(--text-primary)">
                    Rp {{ number_format($acc->current_balance, 0, ',', '.') }}
                </div>
                <div style="font-size:11px;color:var(--text-muted);margin-top:3px">{{ ucfirst($acc->type ?? 'Akun') }}</div>
            </div>
            @endforeach
            @if($totalSavingsBalance > 0)
            <div style="border-left:1px solid var(--border);padding-left:24px">
                <div style="font-size:11px;color:var(--text-muted);margin-bottom:6px;text-transform:uppercase;letter-spacing:.06em;font-weight:600">Dana Dialokasikan</div>
                <div style="font-size:18px;font-weight:700;color:#059669">
                    Rp {{ number_format($totalSavingsBalance, 0, ',', '.') }}
                </div>
                <div style="font-size:11px;color:var(--text-muted);margin-top:3px">{{ $sinkingFunds->count() }} goals / tabungan</div>
            </div>
            @endif
        </div>

    </div>
</div>

{{-- ── Today stat cards ────────────────────────────────────────── --}}
<div class="grid-4 animate-in" style="animation-delay:.04s">

    {{-- Spending today --}}
    <div class="card stat-card red">
        <span class="stat-icon"><i class="fas fa-arrow-trend-down"></i></span>
        <div class="card-title">Pengeluaran Hari Ini</div>
        <div class="card-value">
            Rp {{ number_format($todaySpend, 0, ',', '.') }}
        </div>
        @if($user->daily_budget_idr)
            @php $rem = $user->daily_budget_idr - $todaySpend; $pct = min(round($todaySpend / max($user->daily_budget_idr,1) * 100), 100); @endphp
            <div class="progress-bar" style="margin-top:10px">
                <div class="progress-fill {{ $rem < 0 ? 'red' : ($pct > 75 ? 'orange' : 'green') }}"
                     style="width:{{ $pct }}%"></div>
            </div>
            <div class="card-subtitle" style="margin-top:6px; color: {{ $rem >= 0 ? 'var(--text-muted)' : 'var(--red)' }}">
                @if($rem >= 0)
                    Sisa Rp {{ number_format($rem,0,',','.') }}
                @else
                    <i class="fas fa-triangle-exclamation"></i> Over Rp {{ number_format(abs($rem),0,',','.') }}
                @endif
            </div>
        @endif
    </div>

    {{-- Monthly spend --}}
    <div class="card stat-card orange">
        <span class="stat-icon"><i class="fas fa-calendar"></i></span>
        <div class="card-title">Spending Bulan Ini</div>
        <div class="card-value">
            Rp {{ number_format($monthlySpend, 0, ',', '.') }}
        </div>
        @if($user->monthly_budget_idr)
            @php $mRem = $user->monthly_budget_idr - $monthlySpend; @endphp
            <div class="card-subtitle" style="color: {{ $mRem >= 0 ? 'var(--text-muted)' : 'var(--red)' }}">
                {{ $mRem >= 0 ? 'Sisa Rp '.number_format($mRem,0,',','.') : 'Over Rp '.number_format(abs($mRem),0,',','.') }}
            </div>
        @else
            <div class="card-subtitle">No monthly budget set</div>
        @endif
    </div>

    {{-- Monthly income --}}
    <div class="card stat-card green">
        <span class="stat-icon"><i class="fas fa-coins"></i></span>
        <div class="card-title">Income Bulan Ini</div>
        <div class="card-value">
            Rp {{ number_format($monthlyIncome, 0, ',', '.') }}
        </div>
        @if($monthlySavings > 0)
            <div class="card-subtitle">+Rp {{ number_format($monthlySavings,0,',','.') }} ditabung</div>
        @else
            <div class="card-subtitle" style="color:var(--text-dim)">Belum ada tabungan</div>
        @endif
    </div>

    {{-- Calories / streak --}}
    @if($user->isCalorieMode())
    <div class="card stat-card yellow">
        <span class="stat-icon"><i class="fas fa-fire"></i></span>
        <div class="card-title">Kalori Hari Ini</div>
        <div class="card-value">{{ number_format($todayCalories) }}</div>
        @if($user->daily_calorie_goal)
            @php $cRem = $user->daily_calorie_goal - $todayCalories; $cPct = min(round($todayCalories/max($user->daily_calorie_goal,1)*100),100); @endphp
            <div class="progress-bar" style="margin-top:10px">
                <div class="progress-fill {{ $cRem < 0 ? 'red' : ($cPct > 80 ? 'orange' : 'green') }}"
                     style="width:{{ $cPct }}%"></div>
            </div>
            <div class="card-subtitle" style="margin-top:6px">
                {{ $cRem >= 0 ? 'Sisa '.$cRem.' kcal' : 'Over '.abs($cRem).' kcal' }}
            </div>
        @endif
    </div>
    @else
    <div class="card stat-card accent">
        <span class="stat-icon"><i class="fas fa-fire"></i></span>
        <div class="card-title">Logging Streak</div>
        <div class="card-value">{{ $streak?->log_current ?? 0 }} <span style="font-size:16px;font-weight:500;color:var(--text-muted)">hari</span></div>
        <div class="card-subtitle">Terpanjang: {{ $streak?->log_longest ?? 0 }} hari</div>
    </div>
    @endif

</div>

{{-- ── Financial Health Score ──────────────────────────────────── --}}
@if(!empty($healthData))
<div class="card animate-in" style="animation-delay:.07s">
    @php
        $band  = $healthData['band'];
        $score = $healthData['score'];
        $comps = $healthData['components'];
    @endphp
    <div style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:12px">
        <div>
            <div class="card-title">Financial Health Score</div>
            <div style="display:flex;align-items:baseline;gap:8px">
                <span style="font-size:36px;font-weight:800;color:{{ $band['color'] }}">{{ $score }}</span>
                <span style="font-size:16px;color:var(--text-muted)">/100</span>
                <span style="font-size:13px;font-weight:600;color:{{ $band['color'] }}">{{ $band['label'] }}</span>
            </div>
        </div>
        <a href="{{ route('dashboard.cashflow') }}"
           style="font-size:12px;color:var(--accent);text-decoration:none;border:1px solid var(--accent);
                  padding:6px 12px;border-radius:var(--radius-sm)">Lihat Detail →</a>
    </div>
    {{-- Progress bar --}}
    <div style="height:8px;border-radius:999px;background:var(--border);margin:12px 0;overflow:hidden">
        <div style="height:100%;border-radius:999px;background:{{ $band['color'] }};width:{{ $score }}%;transition:width .6s ease"></div>
    </div>
    {{-- Component breakdown --}}
    <div style="display:grid;grid-template-columns:repeat(3,1fr);gap:8px">
        @foreach($comps as $key => $comp)
        <div style="padding:6px 8px;border-radius:var(--radius-sm);background:var(--bg);border:1px solid var(--border);text-align:center">
            <div style="font-size:18px;font-weight:700;color:var(--text-primary)">{{ $comp['score'] }}<span style="font-size:10px;color:var(--text-dim)">/{{ $comp['max'] }}</span></div>
            <div style="font-size:9px;color:var(--text-dim);margin-top:2px;text-transform:capitalize">{{ str_replace('_', ' ', $key) }}</div>
        </div>
        @endforeach
    </div>
</div>
@endif

{{-- ── 7-day spending chart + category breakdown ───────────────── --}}
<div class="grid-2 animate-in" style="animation-delay:.08s">

    <div class="card" style="margin-bottom:0">
        <div class="card-title">Spending 7 Hari Terakhir</div>
        <div class="chart-container">
            <canvas id="weekSpendChart"></canvas>
        </div>
    </div>

    <div class="card" style="margin-bottom:0">
        <div class="card-title">Kategori Bulan Ini</div>
        @if(!empty($categoryBreakdown))
            <div class="chart-container" style="height:180px">
                <canvas id="catChart"></canvas>
            </div>
            <div style="margin-top:10px">
                @foreach(array_slice($categoryBreakdown, 0, 4, true) as $cat => $amt)
                <div style="display:flex;justify-content:space-between;align-items:center;padding:4px 0;font-size:12px">
                    <span style="color:var(--text-secondary)">{{ str_replace('_',' ', ucfirst($cat)) }}</span>
                    <span style="font-weight:600">Rp {{ number_format($amt,0,',','.') }}</span>
                </div>
                @endforeach
            </div>
        @else
            <div class="empty-state" style="padding:20px">
                <div class="empty-icon"><i class="fas fa-chart-bar"></i></div>
                <p>Belum ada data bulan ini</p>
            </div>
        @endif
    </div>

</div>

{{-- ── Bills due soon ──────────────────────────────────────────── --}}
@if($billsDue->isNotEmpty())
<div class="animate-in" style="animation-delay:.10s">
    <div class="section-title"><i class="fas fa-triangle-exclamation" style="color:var(--orange)"></i> Tagihan Jatuh Tempo Minggu Ini</div>
    <div class="table-wrap">
        @foreach($billsDue as $bill)
        <div class="account-row">
            <div>
                <div class="account-name">{{ $bill->name }}</div>
                <div class="account-type">Jatuh tempo tgl {{ $bill->due_day }}</div>
            </div>
            <div style="text-align:right">
                <div style="font-weight:700;color:var(--red)">Rp {{ number_format($bill->amount,0,',','.') }}</div>
                <div style="font-size:11px;color:var(--text-dim)">{{ $bill->due_day - today()->day }} hari lagi</div>
            </div>
        </div>
        @endforeach
    </div>
</div>
@endif

{{-- ── Streaks — domain-specific ───────────────────────────────── --}}
@if($streak)
@php
    $streakCards = [];
    if ($user->isCalorieMode() && ($streak->log_current ?? 0) > 0)
        $streakCards[] = ['icon' => 'fa-fire', 'label' => 'Logging Streak', 'current' => $streak->log_current, 'longest' => $streak->log_longest, 'color' => 'accent'];
    if ($user->isCalorieMode() && ($streak->meal_current ?? 0) > 0)
        $streakCards[] = ['icon' => 'fa-utensils', 'label' => 'Meal Streak', 'current' => $streak->meal_current ?? 0, 'longest' => $streak->meal_longest ?? 0, 'color' => 'blue'];
    if ($user->isFinanceMode() && ($streak->budget_current ?? 0) > 0)
        $streakCards[] = ['icon' => 'fa-coins', 'label' => 'Budget Streak', 'current' => $streak->budget_current ?? 0, 'longest' => $streak->budget_longest ?? 0, 'color' => 'green'];
    if ($user->isCalorieMode() && $user->daily_calorie_goal && ($streak->calorie_current ?? 0) > 0)
        $streakCards[] = ['icon' => 'fa-bullseye', 'label' => 'Kalori Streak', 'current' => $streak->calorie_current ?? 0, 'longest' => $streak->calorie_longest ?? 0, 'color' => 'orange'];
@endphp
@if(count($streakCards) > 0)
<div class="{{ count($streakCards) === 1 ? 'grid-2' : (count($streakCards) <= 2 ? 'grid-2' : 'grid-4') }} animate-in" style="animation-delay:.11s">
    @foreach($streakCards as $sc)
    <div class="card stat-card {{ $sc['color'] }}" style="margin-bottom:0">
        <span class="stat-icon"><i class="fas {{ $sc['icon'] }}"></i></span>
        <div class="card-title">{{ $sc['label'] }}</div>
        <div class="card-value">{{ $sc['current'] }} <span style="font-size:16px;font-weight:500;color:var(--text-muted)">hari</span></div>
        <div class="card-subtitle">Terpanjang: {{ $sc['longest'] }} hari</div>
    </div>
    @endforeach
</div>
@endif
@endif

{{-- ── Accounts ─────────────────────────────────────────────────── --}}
@if($accounts->isNotEmpty())
<div class="animate-in" style="animation-delay:.12s">
    <div class="section-title">Akun & Wallet</div>
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
            <div class="account-balance">Rp {{ number_format($account->current_balance,0,',','.') }}</div>
        </div>
        @endforeach
        @if($accounts->count() > 1)
        <div class="account-row" style="border-top:1px solid var(--border);margin-top:4px;padding-top:12px;background:var(--bg-hover)">
            <div>
                <div class="account-name" style="font-size:12px;font-weight:600;color:var(--text-secondary)">Total Likuid</div>
                <div class="account-type">{{ $accounts->count() }} akun belanja aktif</div>
            </div>
            <div class="account-balance" style="font-size:17px">Rp {{ number_format($totalBalance,0,',','.') }}</div>
        </div>
        @endif
    </div>
</div>
@endif

{{-- ── Sinking funds / goals ────────────────────────────────────── --}}
@if($sinkingFunds->isNotEmpty())
<div class="animate-in" style="animation-delay:.14s">
    <div class="section-title">Goals & Sinking Funds</div>
    @foreach($sinkingFunds as $fund)
    @php
        $target  = $fund->target_amount ?: 0;
        $pct     = $target > 0 ? min(100, round(($fund->current_balance / $target) * 100)) : 0;
        $onTrack = $fund->on_track ?? null;
    @endphp
    <div class="fund-card" x-data="{open:false}" @click="open=!open" style="cursor:pointer;user-select:none">
        <div class="fund-header">
            <div class="fund-name">
                @if($fund->fund_type === 'emergency_fund') <i class="fas fa-shield-halved" style="color:var(--blue)"></i>
                @elseif($fund->fund_type === 'sinking_fund') <i class="fas fa-piggy-bank" style="color:var(--purple)"></i>
                @elseif($fund->fund_type === 'savings') <i class="fas fa-chart-line" style="color:var(--green)"></i>
                @else <i class="fas fa-bullseye" style="color:var(--orange)"></i>
                @endif
                {{ $fund->name }}
                @if($onTrack !== null)
                    @if($onTrack)
                        <i class="fas fa-circle-check" style="font-size:11px;margin-left:6px;color:var(--green)"></i>
                    @else
                        <i class="fas fa-triangle-exclamation" style="font-size:11px;margin-left:6px;color:var(--orange)"></i>
                    @endif
                @endif
            </div>
            <div class="fund-balance">Rp {{ number_format($fund->current_balance,0,',','.') }}</div>
        </div>
        @if($target > 0)
        <div class="fund-target">Target: Rp {{ number_format($target,0,',','.') }} · {{ $pct }}%</div>
        <div class="progress-track">
            <div class="progress-fill" style="width:{{ $pct }}%;background:{{ $pct >= 100 ? 'var(--green)' : ($pct >= 60 ? 'var(--accent)' : 'var(--text)') }}"></div>
        </div>
        @endif
        @if($fund->target_date)
        <div style="font-size:11px;color:var(--text-dim);margin-top:6px">
            Deadline: {{ \Carbon\Carbon::parse($fund->target_date)->format('d M Y') }}
        </div>
        @endif
        <div x-show="open" x-transition x-cloak style="margin-top:12px;padding-top:12px;border-top:1px solid var(--border)">
            <div style="font-size:11px;color:var(--text-dim);text-transform:uppercase;letter-spacing:.05em;margin-bottom:8px">Rincian Setoran</div>
            @forelse($fund->breakdown ?? [] as $src => $amt)
            <div style="display:flex;justify-content:space-between;font-size:13px;margin-bottom:4px">
                <span style="color:var(--text-muted)">{{ $src }}</span>
                <span style="font-weight:500">Rp {{ number_format($amt,0,',','.') }}</span>
            </div>
            @empty
            <div style="font-size:12px;color:var(--text-dim)">Belum ada setoran.</div>
            @endforelse
        </div>
    </div>
    @endforeach
</div>
@endif

{{-- ── Recent activity ──────────────────────────────────────────── --}}
@if($recentActivities->isNotEmpty())
<div class="animate-in" style="animation-delay:.16s">
    <div class="section-title">Aktivitas Terbaru</div>
    <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th>Aktivitas</th>
                    <th>Tipe</th>
                    <th style="text-align:right">Nominal</th>
                </tr>
            </thead>
            <tbody>
                @foreach($recentActivities as $entry)
                <tr>
                    <td>
                        <div style="font-weight:500">
                            {{ $entry->food_item ?? $entry->note ?? $entry->merchant ?? ucfirst(str_replace('_',' ',$entry->type)) }}
                        </div>
                        <div style="font-size:12px;color:var(--text-dim);margin-top:2px">
                            {{ $entry->entry_time->format('d M · H:i') }}
                        </div>
                    </td>
                    <td><span class="badge badge-{{ $entry->type }}">{{ str_replace('_',' ',$entry->type) }}</span></td>
                    <td style="text-align:right">
                        @if($entry->type === 'meal')
                            <span style="color:var(--orange);font-weight:600">{{ $entry->calories ?? 0 }} kcal</span>
                        @elseif(in_array($entry->type, ['income','saving','sinking_fund_deposit']))
                            <span class="amount-income">+Rp {{ number_format($entry->amount,0,',','.') }}</span>
                        @elseif($entry->amount)
                            <span class="amount-expense">-Rp {{ number_format($entry->amount,0,',','.') }}</span>
                        @else
                            <span style="color:var(--text-dim)">—</span>
                        @endif
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</div>
@endif

<a href="{{ route('dashboard.history') }}" class="animate-in"
   style="animation-delay:.18s;display:flex;align-items:center;justify-content:space-between;
          padding:14px 18px;background:var(--bg-card);border:1px solid var(--border);
          border-radius:var(--radius);text-decoration:none;color:var(--text-secondary);margin-top:4px">
    <span style="font-size:13px;font-weight:500">Lihat semua riwayat</span>
    <span style="color:var(--text-dim)">→</span>
</a>

@endsection

@section('scripts')
<script>
// ── 7-day spending line chart ──────────────────────────────────
const weekData  = @json($spendingChart);

// Build a full 7-day array (fill missing days with 0)
const days = [];
const vals = [];
for (let i = 6; i >= 0; i--) {
    const d = new Date();
    d.setDate(d.getDate() - i);
    const key = d.toISOString().split('T')[0];
    days.push(d.toLocaleDateString('id-ID', { weekday:'short', day:'numeric' }));
    vals.push(weekData[key] || 0);
}

new Chart(document.getElementById('weekSpendChart'), {
    type: 'line',
    data: {
        labels: days,
        datasets: [{
            label: 'Spending',
            data: vals,
            borderColor: '#ef4444',
            backgroundColor: 'rgba(239,68,68,0.08)',
            fill: true,
            tension: 0.4,
            borderWidth: 2,
            pointRadius: 4,
            pointHoverRadius: 6,
            pointBackgroundColor: '#ef4444',
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
            legend: { display: false },
            tooltip: { callbacks: { label: ctx => formatRupiah(ctx.parsed.y) } }
        },
        scales: {
            y: {
                ticks: { callback: v => formatRupiah(v) },
                grid: { color: getComputedStyle(document.documentElement).getPropertyValue('--border').trim() },
                beginAtZero: true
            },
            x: { grid: { display: false } }
        }
    }
});

// ── Category doughnut ──────────────────────────────────────────
@if(!empty($categoryBreakdown))
const catData = @json($categoryBreakdown);
const catColors = [
    '#ef4444','#f97316','#eab308','#22c55e',
    '#3b82f6','#8b5cf6','#ec4899','#06b6d4'
];
const catLabels = Object.keys(catData).map(k => k.replace(/_/g,' ').replace(/\b\w/g, l => l.toUpperCase()));

new Chart(document.getElementById('catChart'), {
    type: 'doughnut',
    data: {
        labels: catLabels,
        datasets: [{
            data: Object.values(catData),
            backgroundColor: catColors.slice(0, Object.keys(catData).length),
            borderWidth: 0,
            hoverOffset: 6,
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        cutout: '68%',
        plugins: {
            legend: { display: false },
            tooltip: { callbacks: { label: ctx => ctx.label + ': ' + formatRupiah(ctx.parsed) } }
        }
    }
});
@endif
</script>
@endsection
