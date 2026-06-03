@extends('layouts.dashboard')
@section('title', 'Distribusi Keuangan — Butler')
@section('content')

<div class="page-header animate-in">
    <div>
        <h2>Distribusi Keuangan</h2>
        <p>Sebaran saldo dan pengeluaran per metode pembayaran bulan ini.</p>
    </div>
</div>

{{-- Summary Cards --}}
<div class="grid-4 animate-in" style="animation-delay:.05s">
    <div class="card stat-card green">
        <div class="stat-icon"><i class="fas fa-coins"></i></div>
        <div class="stat-label">Total Saldo</div>
        <div class="stat-value">Rp {{ number_format($totalBalance, 0, ',', '.') }}</div>
        <div class="stat-sub">{{ $accounts->count() }} akun aktif</div>
    </div>
    <div class="card stat-card red">
        <div class="stat-icon"><i class="fas fa-money-bill-wave"></i></div>
        <div class="stat-label">Pengeluaran Bulan Ini</div>
        <div class="stat-value">Rp {{ number_format(array_sum($spendingByType), 0, ',', '.') }}</div>
    </div>
    <div class="card stat-card blue">
        <div class="stat-icon"><i class="fas fa-building-columns"></i></div>
        <div class="stat-label">Jumlah Akun</div>
        <div class="stat-value">{{ $accounts->count() }}</div>
    </div>
    <div class="card stat-card orange">
        <div class="stat-icon"><i class="fas fa-chart-pie"></i></div>
        <div class="stat-label">Tipe Akun</div>
        <div class="stat-value">{{ count($byType) }}</div>
    </div>
</div>

@if($accounts->isEmpty())
<div class="card animate-in" style="animation-delay:.1s">
    <div class="empty-state">
        <div class="empty-icon"><i class="fas fa-credit-card"></i></div>
        <h3>Belum Ada Akun</h3>
        <p>Tambah akun bank, e-wallet, atau cash melalui Telegram untuk melihat distribusi keuangan kamu.</p>
    </div>
</div>
@else

{{-- Charts row --}}
<div class="animate-in" style="animation-delay:.1s;display:grid;grid-template-columns:1fr 1fr;gap:14px;margin-bottom:14px">
    @media (max-width:640px) { .chart-row { grid-template-columns:1fr!important; } }
    {{-- Balance Distribution (doughnut) --}}
    <div class="card">
        <div class="card-title">Distribusi Saldo</div>
        <div class="chart-container" style="height:200px">
            <canvas id="balanceChart"></canvas>
        </div>
    </div>

    {{-- Spending by Method (horizontal bar) --}}
    <div class="card">
        <div class="card-title">Pengeluaran Bulan Ini per Metode</div>
        <div class="chart-container" style="height:200px">
            <canvas id="spendingChart"></canvas>
        </div>
    </div>
</div>

{{-- Per-account table --}}
<div class="animate-in" style="animation-delay:.15s">
    <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th>Akun</th>
                    <th>Tipe</th>
                    <th style="text-align:right">Saldo</th>
                    <th style="text-align:right">% Total</th>
                    <th style="text-align:right">Pengeluaran</th>
                    <th style="text-align:right">Transaksi</th>
                </tr>
            </thead>
            <tbody>
                @foreach($accounts->sortByDesc('current_balance') as $account)
                <tr>
                    <td>
                        <span style="font-weight:600;color:var(--text-primary)">{{ $account->name }}</span>
                        @if($account->is_default_spending)
                            <span style="font-size:10px;background:rgba(139,92,246,.15);color:#a78bfa;padding:2px 7px;border-radius:999px;margin-left:6px;font-weight:600">DEFAULT</span>
                        @endif
                    </td>
                    <td>
                        @php $typeIcon = match($account->type ?? 'other') {
                            'bank' => 'fa-building-columns', 'ewallet' => 'fa-mobile-screen', 'emoney' => 'fa-credit-card', 'cash' => 'fa-money-bill', 'credit_card' => 'fa-credit-card', default => 'fa-folder'
                        }; @endphp
                        <span style="font-size:13px;color:var(--text-secondary)"><i class="fas {{ $typeIcon }}"></i> {{ ucfirst($account->type ?? 'other') }}</span>
                    </td>
                    <td style="text-align:right;font-weight:700;color:var(--text-primary)">Rp {{ number_format($account->current_balance, 0, ',', '.') }}</td>
                    <td style="text-align:right;color:var(--text-secondary)">
                        {{ $totalBalance > 0 ? round(($account->current_balance / $totalBalance) * 100) : 0 }}%
                    </td>
                    <td style="text-align:right;color:#ef4444">
                        @if($account->month_spending > 0)
                            Rp {{ number_format($account->month_spending, 0, ',', '.') }}
                        @else
                            <span style="color:var(--text-muted)">—</span>
                        @endif
                    </td>
                    <td style="text-align:right;color:var(--text-secondary)">{{ $account->month_tx_count ?? 0 }}</td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</div>

{{-- Grouped by type --}}
@foreach($byType as $type => $group)
<div class="animate-in" style="animation-delay:.2s;margin-bottom:12px">
    <div class="card">
        <div class="card-title" style="margin-bottom:10px">{{ $group['label'] }}</div>
        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:8px">
            <span style="font-weight:700;font-size:18px;color:var(--text-primary)">Rp {{ number_format($group['balance'], 0, ',', '.') }}</span>
            <span style="font-size:13px;color:var(--text-muted)">{{ $totalBalance > 0 ? round(($group['balance'] / $totalBalance) * 100) : 0 }}% dari total</span>
        </div>
        <div class="progress-bar">
            <div class="progress-fill blue" style="width:{{ $totalBalance > 0 ? min(100, round(($group['balance'] / $totalBalance) * 100)) : 0 }}%"></div>
        </div>
        @if(($spendingByType[$type] ?? 0) > 0)
        <div style="margin-top:8px;font-size:12px;color:var(--text-muted)">
            Pengeluaran bulan ini: <strong style="color:#ef4444">Rp {{ number_format($spendingByType[$type], 0, ',', '.') }}</strong>
        </div>
        @endif
    </div>
</div>
@endforeach
@endif

@endsection
@section('scripts')
<script>
@if(!$accounts->isEmpty())
const balanceLabels = @json(collect($byType)->keys()->map(fn($t) => match($t) { 'bank'=>'Bank','ewallet'=>'E-Wallet','emoney'=>'E-Money','cash'=>'Cash','credit_card'=>'Kredit',default=>'Lain' })->values());
const balanceData   = @json(collect($byType)->pluck('balance')->values());
const spendingLabels = @json(collect($spendingByType)->keys()->map(fn($t) => match($t) { 'bank'=>'Bank','ewallet'=>'E-Wallet','emoney'=>'E-Money','cash'=>'Cash','credit_card'=>'Kredit',default=>'Lain' })->values());
const spendingData   = @json(collect($spendingByType)->values());

const palette = ['#8b5cf6','#3b82f6','#10b981','#f97316','#ef4444','#eab308'];

new Chart(document.getElementById('balanceChart'), {
    type: 'doughnut',
    data: {
        labels: balanceLabels,
        datasets: [{ data: balanceData, backgroundColor: palette, borderWidth: 0, hoverOffset: 6 }]
    },
    options: {
        responsive: true, maintainAspectRatio: false,
        plugins: { legend: { position: 'bottom', labels: { boxWidth: 10, padding: 14, font: { size: 12 } } } },
        cutout: '62%'
    }
});

if (spendingData.some(v => v > 0)) {
    new Chart(document.getElementById('spendingChart'), {
        type: 'bar',
        data: {
            labels: spendingLabels,
            datasets: [{ data: spendingData, backgroundColor: palette, borderRadius: 6, borderSkipped: false }]
        },
        options: {
            indexAxis: 'y',
            responsive: true, maintainAspectRatio: false,
            plugins: { legend: { display: false } },
            scales: {
                x: { grid: { color: 'rgba(255,255,255,.04)' }, ticks: { callback: v => v >= 1000000 ? 'Rp'+(v/1000000).toFixed(1)+'jt' : 'Rp'+(v/1000).toFixed(0)+'k' } },
                y: { grid: { display: false } }
            }
        }
    });
}
@endif
</script>
@endsection
