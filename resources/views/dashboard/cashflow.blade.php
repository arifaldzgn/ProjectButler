@extends('layouts.dashboard')
@section('title', 'Cashflow — Butler')
@section('content')

<div class="page-header animate-in">
    <div>
        <h2>Cashflow Health</h2>
        <p>Tren income vs pengeluaran 6 bulan terakhir dan pertumbuhan tabungan.</p>
    </div>
</div>

{{-- Health Score + Key Stats --}}
<div class="grid-4 animate-in" style="animation-delay:.05s">
    {{-- Health gauge card --}}
    <div class="card stat-card {{ $healthScore >= 60 ? 'green' : ($healthScore >= 30 ? 'orange' : 'red') }}" style="grid-column:span 1">
        <div class="stat-icon"><i class="fas fa-heart-pulse"></i></div>
        <div class="stat-label">Health Score</div>
        <div class="stat-value">{{ $healthScore }}<span style="font-size:14px;font-weight:400">/100</span></div>
        <div style="margin-top:8px">
            <div class="progress-bar" style="height:8px">
                <div class="progress-fill {{ $healthScore >= 60 ? 'green' : ($healthScore >= 30 ? 'orange' : 'red') }}"
                     style="width:{{ $healthScore }}%"></div>
            </div>
        </div>
        <div class="stat-sub" style="margin-top:6px">
            {{ $healthScore >= 60 ? 'Cashflow sehat' : ($healthScore >= 30 ? 'Bisa ditingkatkan' : 'Perlu perhatian') }}
        </div>
    </div>

    <div class="card stat-card blue">
        <div class="stat-icon"><i class="fas fa-coins"></i></div>
        <div class="stat-label">Expense Ratio</div>
        <div class="stat-value">{{ $expenseRatio }}%</div>
        <div class="stat-sub">dari income bulan ini</div>
    </div>

    <div class="card stat-card green">
        <div class="stat-icon"><i class="fas fa-building-columns"></i></div>
        <div class="stat-label">Total Tabungan</div>
        <div class="stat-value">Rp {{ $totalSavingsBalance >= 1000000 ? number_format($totalSavingsBalance/1000000,1).'jt' : number_format($totalSavingsBalance/1000,0).'k' }}</div>
        <div class="stat-sub">semua tipe dana</div>
    </div>

    <div class="card stat-card {{ isset($months[$bestMonth]) && $months[$bestMonth]['net'] > 0 ? 'green' : 'orange' }}">
        <div class="stat-icon"><i class="fas fa-trophy"></i></div>
        <div class="stat-label">Bulan Terbaik</div>
        <div class="stat-value" style="font-size:16px">{{ isset($months[$bestMonth]) ? $months[$bestMonth]['label'] : '—' }}</div>
        <div class="stat-sub">
            @if(isset($months[$bestMonth]) && $months[$bestMonth]['net'] > 0)
                +Rp {{ number_format($months[$bestMonth]['net']/1000, 0) }}k net
            @else
                belum ada data positif
            @endif
        </div>
    </div>
</div>

{{-- Financial Health Score Detail --}}
@if(!empty($healthData))
<div class="card animate-in" style="animation-delay:.09s">
    @php $band = $healthData['band']; @endphp
    <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:14px">
        <div class="card-title" style="margin:0">Financial Health Score</div>
        <div style="display:flex;align-items:center;gap:8px">
            <span style="font-size:28px;font-weight:800;color:{{ $band['color'] }}">{{ $healthData['score'] }}</span>
            <span style="font-size:14px;color:var(--text-muted)">/100</span>
            <span style="font-weight:600;font-size:13px;color:{{ $band['color'] }}">{{ $band['label'] }}</span>
        </div>
    </div>
    <div style="height:8px;border-radius:999px;background:var(--border);margin-bottom:16px;overflow:hidden">
        <div style="height:100%;border-radius:999px;background:{{ $band['color'] }};width:{{ $healthData['score'] }}%;transition:width .6s"></div>
    </div>
    <div style="display:grid;grid-template-columns:repeat(2,1fr);gap:10px">
        @foreach($healthData['components'] as $key => $comp)
        <div style="padding:10px 12px;border-radius:var(--radius-sm);background:var(--bg);border:1px solid var(--border)">
            <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:4px">
                <span style="font-size:11px;color:var(--text-muted);text-transform:capitalize">{{ str_replace('_', ' ', $key) }}</span>
                <span style="font-weight:700;font-size:13px;color:var(--text-primary)">{{ $comp['score'] }}<span style="font-size:10px;font-weight:400;color:var(--text-dim)">/{{ $comp['max'] }}</span></span>
            </div>
            <div style="height:4px;border-radius:999px;background:var(--border);overflow:hidden">
                <div style="height:100%;border-radius:999px;background:var(--accent);width:{{ round($comp['score']/$comp['max']*100) }}%"></div>
            </div>
            @if(isset($comp['pct']))
            <div style="font-size:10px;color:var(--text-dim);margin-top:3px">{{ $comp['pct'] }}% savings rate</div>
            @elseif(isset($comp['months_covered']))
            <div style="font-size:10px;color:var(--text-dim);margin-top:3px">{{ $comp['months_covered'] }} bulan tersimpan</div>
            @elseif(isset($comp['dti_pct']))
            <div style="font-size:10px;color:var(--text-dim);margin-top:3px">DTI: {{ $comp['dti_pct'] }}%</div>
            @elseif(isset($comp['streak_days']))
            <div style="font-size:10px;color:var(--text-dim);margin-top:3px">{{ $comp['streak_days'] }} hari streak</div>
            @endif
        </div>
        @endforeach
    </div>
    @if(!empty($healthData['cached']))
    <div style="font-size:10px;color:var(--text-dim);margin-top:8px;text-align:right">Diperbarui {{ $healthData['age_hours'] }} jam lalu</div>
    @endif
</div>
@endif

{{-- Monthly Income vs Expense Chart --}}
<div class="card animate-in" style="animation-delay:.1s">
    <div class="card-title">Income vs Pengeluaran (6 Bulan)</div>
    <div class="chart-container" style="height:240px">
        <canvas id="incomeExpenseChart"></canvas>
    </div>
    <div class="chart-legend" style="margin-top:12px">
        <div class="chart-legend-item"><div class="chart-legend-dot" style="background:#10b981"></div> Income</div>
        <div class="chart-legend-item"><div class="chart-legend-dot" style="background:#ef4444"></div> Pengeluaran</div>
        <div class="chart-legend-item"><div class="chart-legend-dot" style="background:#8b5cf6"></div> Net Savings</div>
    </div>
</div>

{{-- Savings Growth Chart --}}
<div class="card animate-in" style="animation-delay:.15s">
    <div class="card-title">Akumulasi Tabungan Bulanan</div>
    <div class="chart-container" style="height:200px">
        <canvas id="savingsGrowthChart"></canvas>
    </div>
</div>

{{-- Monthly breakdown table --}}
<div class="animate-in" style="animation-delay:.2s">
    <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th>Bulan</th>
                    <th style="text-align:right">Income</th>
                    <th style="text-align:right">Pengeluaran</th>
                    <th style="text-align:right">Tabungan</th>
                    <th style="text-align:right">Net</th>
                    <th style="text-align:right">Status</th>
                </tr>
            </thead>
            <tbody>
                @foreach(array_reverse($months) as $key => $month)
                <tr>
                    <td style="font-weight:600;color:var(--text-primary)">
                        {{ $month['label'] }}
                        @if($key === $bestMonth)
                            <span style="font-size:10px;background:rgba(16,185,129,.15);color:#10b981;padding:2px 7px;border-radius:999px;margin-left:6px">TERBAIK</span>
                        @endif
                        @if($key === $worstMonth && $key !== $bestMonth)
                            <span style="font-size:10px;background:rgba(239,68,68,.12);color:#ef4444;padding:2px 7px;border-radius:999px;margin-left:6px">TERENDAH</span>
                        @endif
                    </td>
                    <td style="text-align:right;color:#10b981;font-weight:500">
                        {{ $month['income'] > 0 ? 'Rp '.number_format($month['income'],0,',','.') : '—' }}
                    </td>
                    <td style="text-align:right;color:#ef4444;font-weight:500">
                        {{ $month['expenses'] > 0 ? 'Rp '.number_format($month['expenses'],0,',','.') : '—' }}
                    </td>
                    <td style="text-align:right;color:#8b5cf6;font-weight:500">
                        {{ $month['savings'] > 0 ? 'Rp '.number_format($month['savings'],0,',','.') : '—' }}
                    </td>
                    <td style="text-align:right;font-weight:700;color:{{ $month['net'] >= 0 ? '#10b981' : '#ef4444' }}">
                        {{ $month['net'] >= 0 ? '+' : '' }}Rp {{ number_format($month['net'],0,',','.') }}
                    </td>
                    <td style="text-align:right">
                        @if($month['income'] == 0 && $month['expenses'] == 0)
                            <span style="color:var(--text-muted);font-size:12px">—</span>
                        @elseif($month['net'] >= 0)
                            <span class="pill pill-success">surplus</span>
                        @else
                            <span class="pill pill-danger">defisit</span>
                        @endif
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</div>

@endsection
@section('scripts')
<script>
const monthLabels  = @json(collect($months)->pluck('label')->values());
const incomeData   = @json(collect($months)->pluck('income')->values());
const expenseData  = @json(collect($months)->pluck('expenses')->values());
const netData      = @json(collect($months)->pluck('net')->values());
const savingsGrowth = @json(array_values($cumulativeSavings));

new Chart(document.getElementById('incomeExpenseChart'), {
    type: 'bar',
    data: {
        labels: monthLabels,
        datasets: [
            { label: 'Income',       data: incomeData,  backgroundColor: 'rgba(16,185,129,.65)', borderRadius: 5, order: 2 },
            { label: 'Pengeluaran',  data: expenseData, backgroundColor: 'rgba(239,68,68,.65)',  borderRadius: 5, order: 2 },
            { label: 'Net Savings',  data: netData,     type: 'line', borderColor: '#8b5cf6', backgroundColor: 'transparent',
              tension: .35, pointRadius: 4, pointBackgroundColor: '#8b5cf6', borderWidth: 2, order: 1 }
        ]
    },
    options: {
        responsive: true, maintainAspectRatio: false,
        plugins: { legend: { display: false } },
        scales: {
            x: { grid: { display: false } },
            y: { grid: { color: 'rgba(255,255,255,.04)' },
                 ticks: { callback: v => v >= 1000000 ? 'Rp'+(v/1000000).toFixed(1)+'jt' : 'Rp'+(v/1000).toFixed(0)+'k' } }
        }
    }
});

new Chart(document.getElementById('savingsGrowthChart'), {
    type: 'line',
    data: {
        labels: monthLabels,
        datasets: [{
            label: 'Akumulasi',
            data: savingsGrowth,
            borderColor: '#8b5cf6',
            backgroundColor: 'rgba(139,92,246,.12)',
            fill: true, tension: .4, pointRadius: 4, pointBackgroundColor: '#8b5cf6', borderWidth: 2
        }]
    },
    options: {
        responsive: true, maintainAspectRatio: false,
        plugins: { legend: { display: false } },
        scales: {
            x: { grid: { display: false } },
            y: { grid: { color: 'rgba(255,255,255,.04)' },
                 ticks: { callback: v => v >= 1000000 ? 'Rp'+(v/1000000).toFixed(1)+'jt' : 'Rp'+(v/1000).toFixed(0)+'k' } }
        }
    }
});
</script>
@endsection
