@extends('layouts.dashboard')
@section('title', 'Hutang & Tagihan — Butler')
@section('content')

<div class="page-header animate-in">
    <div>
        <h2>Hutang & Tagihan</h2>
        <p>Kelola cicilan dan tagihan. Tidak ada yang terlewat.</p>
    </div>
</div>

{{-- Summary Cards --}}
<div class="grid-4 animate-in" style="animation-delay:.05s">
    <div class="card stat-card red">
        <div class="stat-icon"><i class="fas fa-credit-card"></i></div>
        <div class="stat-label">Total Hutang</div>
        <div class="stat-value">
            @if($totalDebt >= 1000000)
                Rp {{ number_format($totalDebt/1000000, 1) }}jt
            @else
                Rp {{ number_format($totalDebt/1000, 0) }}k
            @endif
        </div>
        <div class="stat-sub">{{ $debts->count() }} cicilan aktif</div>
    </div>
    <div class="card stat-card orange">
        <div class="stat-icon"><i class="fas fa-calendar"></i></div>
        <div class="stat-label">Kewajiban Bulanan</div>
        <div class="stat-value">
            @if($monthlyObligations >= 1000000)
                Rp {{ number_format($monthlyObligations/1000000, 1) }}jt
            @else
                Rp {{ number_format($monthlyObligations/1000, 0) }}k
            @endif
        </div>
        <div class="stat-sub">cicilan + tagihan</div>
    </div>
    <div class="card stat-card {{ $billsPaid === $billsTotal && $billsTotal > 0 ? 'green' : 'blue' }}">
        <div class="stat-icon"><i class="fas fa-receipt"></i></div>
        <div class="stat-label">Tagihan Bulan Ini</div>
        <div class="stat-value">{{ $billsPaid }}/{{ $billsTotal }}</div>
        <div class="stat-sub">{{ $billsTotal > 0 ? round(($billsPaid/$billsTotal)*100) : 0 }}% lunas</div>
    </div>
    <div class="card stat-card {{ $overallPayoff ? 'accent' : 'blue' }}">
        <div class="stat-icon"><i class="fas fa-bullseye"></i></div>
        <div class="stat-label">Estimasi Lunas</div>
        <div class="stat-value" style="font-size:16px">{{ $overallPayoff ?? '—' }}</div>
        <div class="stat-sub">berdasarkan cicilan saat ini</div>
    </div>
</div>

@if($debts->isNotEmpty())
{{-- Debt progress + payoff priority --}}
<div class="animate-in" style="animation-delay:.1s;margin-bottom:14px">
    <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:10px">
        <h3 style="font-size:15px;font-weight:600;color:var(--text-primary)"><i class="fas fa-money-bill-wave" style="margin-right:6px;color:var(--red)"></i> Cicilan Aktif</h3>
        <span style="font-size:12px;color:var(--text-muted)">Diurutkan: bunga tertinggi (avalanche)</span>
    </div>

    @foreach($debts as $i => $debt)
    @php
        $progressColor = $debt->progress_pct >= 75 ? 'green' : ($debt->progress_pct >= 40 ? 'blue' : 'orange');
        $isHighInterest = $i === 0 && $debt->interest_rate > 0;
    @endphp
    <div class="card" style="margin-bottom:10px{{ $isHighInterest ? ';border-color:rgba(239,68,68,.3)' : '' }}">
        <div style="display:flex;justify-content:space-between;align-items:flex-start;gap:12px;margin-bottom:12px">
            <div>
                <div style="display:flex;align-items:center;gap:8px">
                    <span style="font-weight:700;font-size:15px;color:var(--text-primary)">{{ $debt->name }}</span>
                    @if($isHighInterest)
                        <span style="font-size:10px;background:rgba(239,68,68,.15);color:#ef4444;padding:2px 8px;border-radius:999px;font-weight:700">PRIORITAS</span>
                    @endif
                    @if($debt->this_month_paid)
                        <span style="font-size:10px;background:rgba(16,185,129,.15);color:#10b981;padding:2px 8px;border-radius:999px;font-weight:700"><i class="fas fa-check" style="font-size:8px"></i> LUNAS BLN INI</span>
                    @endif
                </div>
                <div style="font-size:12px;color:var(--text-muted);margin-top:3px">
                    {{ $debt->type_label }}
                    @if($debt->interest_rate > 0)
                        · {{ $debt->interest_rate }}% p.a.
                    @endif
                    · Jatuh tgl {{ $debt->due_day }}
                </div>
            </div>
            <div style="text-align:right;flex-shrink:0">
                <div style="font-weight:700;font-size:16px;color:var(--text-primary)">
                    Rp {{ number_format($debt->remaining_balance, 0, ',', '.') }}
                </div>
                <div style="font-size:12px;color:var(--text-muted)">sisa hutang</div>
            </div>
        </div>

        {{-- Progress bar --}}
        <div style="margin-bottom:8px">
            <div style="display:flex;justify-content:space-between;font-size:11px;color:var(--text-muted);margin-bottom:4px">
                <span>{{ $debt->progress_pct }}% terlunasi</span>
                <span>Total: Rp {{ number_format($debt->total_amount, 0, ',', '.') }}</span>
            </div>
            <div class="progress-bar" style="height:8px">
                <div class="progress-fill {{ $progressColor }}" style="width:{{ $debt->progress_pct }}%"></div>
            </div>
        </div>

        <div style="display:grid;grid-template-columns:repeat(3,1fr);gap:8px;margin-top:8px">
            <div style="text-align:center;padding:8px;background:var(--bg-hover);border-radius:8px">
                <div style="font-size:11px;color:var(--text-muted)">Cicilan/bln</div>
                <div style="font-weight:600;font-size:13px;color:var(--text-primary)">Rp {{ number_format($debt->monthly_installment, 0, ',', '.') }}</div>
            </div>
            <div style="text-align:center;padding:8px;background:var(--bg-hover);border-radius:8px">
                <div style="font-size:11px;color:var(--text-muted)">Sisa Bulan</div>
                <div style="font-weight:600;font-size:13px;color:var(--text-primary)">{{ $debt->months_remaining ?? '—' }}</div>
            </div>
            <div style="text-align:center;padding:8px;background:var(--bg-hover);border-radius:8px">
                <div style="font-size:11px;color:var(--text-muted)">Lunas</div>
                <div style="font-weight:600;font-size:13px;color:var(--text-primary)">{{ $debt->projected_payoff ?? '—' }}</div>
            </div>
        </div>
    </div>
    @endforeach
</div>
@else
<div class="card animate-in" style="animation-delay:.1s">
    <div class="empty-state">
        <div class="empty-icon"><i class="fas fa-champagne-glasses"></i></div>
        <h3>Bebas Hutang!</h3>
        <p>Tidak ada cicilan aktif. Atau tambahkan cicilan lewat Telegram: <code>bayar cicilan motor 800rb</code></p>
    </div>
</div>
@endif

{{-- Bill Calendar --}}
@if($bills->isNotEmpty())
<div class="animate-in" style="animation-delay:.2s;margin-bottom:14px">
    <h3 style="font-size:15px;font-weight:600;color:var(--text-primary);margin-bottom:12px"><i class="fas fa-receipt" style="margin-right:6px;color:var(--blue)"></i> Tagihan Bulan Ini</h3>

    {{-- Calendar-style grid --}}
    <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(150px,1fr));gap:10px">
        @foreach($bills->sortBy('due_day') as $bill)
        @php
            $daysLeft = $bill->due_day - today()->day;
            $isPast = $daysLeft < 0;
            $isUrgent = !$bill->this_month_paid && $daysLeft >= 0 && $daysLeft <= 3;
            $border = $bill->this_month_paid ? 'rgba(16,185,129,.3)' : ($isUrgent ? 'rgba(239,68,68,.35)' : 'var(--border)');
        @endphp
        <div style="padding:14px;background:var(--bg-card);border:1px solid {{ $border }};border-radius:var(--radius-sm)">
            <div style="font-size:11px;font-weight:600;letter-spacing:.04em;text-transform:uppercase;
                        color:{{ $bill->this_month_paid ? '#10b981' : ($isUrgent ? '#ef4444' : 'var(--text-muted)') }};margin-bottom:6px">
                @if($bill->this_month_paid) <i class="fas fa-check"></i> LUNAS
                @elseif($isUrgent) <i class="fas fa-triangle-exclamation"></i> {{ $daysLeft == 0 ? 'HARI INI' : $daysLeft.' HARI LAGI' }}
                @elseif($isPast) LEWAT
                @else TGL {{ $bill->due_day }}
                @endif
            </div>
            <div style="font-weight:600;color:var(--text-primary);font-size:14px;margin-bottom:3px">{{ $bill->name }}</div>
            <div style="font-weight:700;color:{{ $bill->this_month_paid ? '#10b981' : 'var(--text-primary)' }};font-size:15px">
                Rp {{ number_format($bill->amount, 0, ',', '.') }}
            </div>
        </div>
        @endforeach
    </div>
</div>
@endif

{{-- Debt composition chart --}}
@if($debts->isNotEmpty() && $debtByType->isNotEmpty())
<div class="card animate-in" style="animation-delay:.25s">
    <div class="card-title">Komposisi Hutang per Tipe</div>
    <div class="chart-container" style="height:180px">
        <canvas id="debtTypeChart"></canvas>
    </div>
</div>
@endif

{{-- Avalanche tip --}}
@if($debts->count() > 1)
<div class="animate-in" style="animation-delay:.3s;padding:14px 16px;background:rgba(139,92,246,.08);border:1px solid rgba(139,92,246,.2);border-radius:var(--radius-sm);font-size:13px;color:var(--text-secondary)">
    <i class="fas fa-lightbulb" style="color:var(--yellow);margin-right:4px"></i> <strong style="color:var(--text-primary)">Strategi Avalanche:</strong>
    Lunasi <strong>{{ $debts->first()->name }}</strong> dulu (bunga {{ $debts->first()->interest_rate }}% tertinggi) sambil bayar minimum di cicilan lain.
    Cara ini menghemat bunga terbesar.
</div>
@endif

@endsection
@section('scripts')
<script>
@if($debts->isNotEmpty() && $debtByType->isNotEmpty())
const debtTypeLabels = @json($debtByType->keys()->map(fn($t) => match($t) {
    'installment'=>'Cicilan','credit_card'=>'Kredit','personal_loan'=>'Pinjaman','mortgage'=>'KPR','paylater'=>'Paylater',default=>'Lain'
})->values());
const debtTypeData = @json($debtByType->values());

new Chart(document.getElementById('debtTypeChart'), {
    type: 'doughnut',
    data: {
        labels: debtTypeLabels,
        datasets: [{ data: debtTypeData, backgroundColor: ['#ef4444','#f97316','#eab308','#8b5cf6','#3b82f6'], borderWidth: 0, hoverOffset: 6 }]
    },
    options: {
        responsive: true, maintainAspectRatio: false,
        plugins: { legend: { position: 'bottom', labels: { boxWidth: 10, padding: 14, font: { size: 12 } } } },
        cutout: '62%'
    }
});
@endif
</script>
@endsection
