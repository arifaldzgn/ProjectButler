@extends('layouts.dashboard')
@section('title', 'Timeline Keuangan — Butler')
@section('content')

<div class="page-header animate-in">
    <div>
        <h2>Timeline Keuangan</h2>
        <p>Semua transaksi hari ini dengan saldo berjalan.</p>
    </div>
</div>

{{-- Date navigation --}}
<div class="animate-in" style="animation-delay:.05s;display:flex;align-items:center;gap:12px;margin-bottom:20px;flex-wrap:wrap">
    <a href="?date={{ $date->copy()->subDay()->toDateString() }}"
       style="padding:8px 14px;border:1px solid var(--border);border-radius:var(--radius-sm);text-decoration:none;
              color:var(--text-secondary);font-size:13px;font-weight:500;display:inline-flex;align-items:center;gap:6px">
        ← Sebelumnya
    </a>

    <form method="GET" style="display:inline-flex;align-items:center;gap:8px">
        <input type="date" name="date" value="{{ $date->toDateString() }}"
               class="field-input" style="width:auto;padding:8px 12px"
               onchange="this.form.submit()">
    </form>

    @if(!$date->isToday())
    <a href="?date={{ today()->toDateString() }}"
       style="padding:8px 14px;border:1px solid var(--border);border-radius:var(--radius-sm);text-decoration:none;
              color:var(--text-secondary);font-size:13px;font-weight:500">
        Hari Ini
    </a>
    @endif

    @if($date->lt(today()))
    <a href="?date={{ $date->copy()->addDay()->toDateString() }}"
       style="padding:8px 14px;border:1px solid var(--border);border-radius:var(--radius-sm);text-decoration:none;
              color:var(--text-secondary);font-size:13px;font-weight:500">
        Berikutnya →
    </a>
    @endif

    <span style="font-size:14px;font-weight:600;color:var(--text-primary);margin-left:auto">
        {{ $date->translatedFormat('l, d F Y') }}
    </span>
</div>

{{-- Daily Summary Cards --}}
<div class="grid-3 animate-in" style="animation-delay:.08s">
    <div class="card stat-card green">
        <div class="stat-icon"><i class="fas fa-arrow-down"></i></div>
        <div class="stat-label">Total Masuk</div>
        <div class="stat-value">Rp {{ number_format($totalIn, 0, ',', '.') }}</div>
    </div>
    <div class="card stat-card red">
        <div class="stat-icon"><i class="fas fa-arrow-up"></i></div>
        <div class="stat-label">Total Keluar</div>
        <div class="stat-value">Rp {{ number_format($totalOut, 0, ',', '.') }}</div>
    </div>
    <div class="card stat-card {{ ($totalIn - $totalOut) >= 0 ? 'green' : 'red' }}">
        <div class="stat-icon"><i class="fas fa-scale-balanced"></i></div>
        <div class="stat-label">Net Hari Ini</div>
        <div class="stat-value">
            {{ ($totalIn - $totalOut) >= 0 ? '+' : '' }}Rp {{ number_format($totalIn - $totalOut, 0, ',', '.') }}
        </div>
    </div>
</div>

{{-- Transaction Timeline --}}
<div class="card animate-in" style="animation-delay:.12s">
    <div class="card-title" style="margin-bottom:16px"><i class="fas fa-list" style="margin-right:5px"></i> Transaksi</div>

    @if($entries->isEmpty())
    <div class="empty-state" style="padding:32px 0">
        <div class="empty-icon"><i class="fas fa-inbox"></i></div>
        <h3>Tidak ada transaksi</h3>
        <p>Belum ada catatan untuk tanggal ini.</p>
    </div>
    @else

    <div style="position:relative;padding-left:24px">
        {{-- Vertical line --}}
        <div style="position:absolute;left:7px;top:8px;bottom:8px;width:2px;background:var(--border);border-radius:1px"></div>

        @foreach($entries as $entry)
        @php
            $isIn = in_array($entry->type, ['income']);
            $isOut = in_array($entry->type, ['expense','bill_payment','debt_payment','saving','sinking_fund_deposit']);
            $dotColor = $isIn ? '#10b981' : ($entry->type === 'meal' ? '#f97316' : ($entry->type === 'transfer' ? '#3b82f6' : '#ef4444'));
            $icon = match($entry->type) {
                'expense' => 'fa-arrow-trend-down', 'income' => 'fa-coins', 'meal' => 'fa-utensils',
                'saving' => 'fa-piggy-bank', 'sinking_fund_deposit' => 'fa-bullseye',
                'bill_payment' => 'fa-receipt', 'debt_payment' => 'fa-credit-card',
                'transfer' => 'fa-arrows-rotate', default => 'fa-pen-to-square'
            };
            $desc = $entry->food_item ?? $entry->merchant ?? $entry->note ?? ucfirst(str_replace('_',' ',$entry->type));
        @endphp

        <div style="position:relative;margin-bottom:16px;padding:14px 16px;background:var(--bg-elevated);border:1px solid var(--border);border-radius:var(--radius-sm)">
            {{-- Timeline dot --}}
            <div style="position:absolute;left:-20px;top:18px;width:10px;height:10px;border-radius:50%;background:{{ $dotColor }};border:2px solid var(--bg);box-shadow:0 0 0 1px {{ $dotColor }}"></div>

            <div style="display:flex;justify-content:space-between;align-items:flex-start;gap:8px">
                <div style="display:flex;align-items:center;gap:8px;flex:1;min-width:0">
                    <i class="fas {{ $icon }}" style="font-size:16px;color:{{ $dotColor }};width:20px;text-align:center;flex-shrink:0"></i>
                    <div style="min-width:0">
                        <div style="font-weight:600;color:var(--text-primary);font-size:14px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis">
                            {{ $desc }}
                        </div>
                        <div style="font-size:12px;color:var(--text-muted);margin-top:2px">
                            {{ \Carbon\Carbon::parse($entry->entry_time)->format('H:i') }}
                            @if($entry->calories)
                                · {{ $entry->calories }} kcal
                            @endif
                        </div>
                    </div>
                </div>
                <div style="text-align:right;flex-shrink:0">
                    @if($entry->amount)
                    <div style="font-weight:700;font-size:15px;color:{{ $isIn ? '#10b981' : ($entry->type === 'meal' ? 'var(--text-secondary)' : '#ef4444') }}">
                        {{ $isIn ? '+' : ($entry->type !== 'meal' ? '-' : '') }}Rp {{ number_format($entry->amount, 0, ',', '.') }}
                    </div>
                    @endif
                    @if(isset($entry->running_balance))
                    <div style="font-size:11px;color:var(--text-muted);margin-top:2px">
                        saldo: Rp {{ number_format($entry->running_balance, 0, ',', '.') }}
                    </div>
                    @endif
                </div>
            </div>
        </div>
        @endforeach
    </div>
    @endif
</div>

{{-- Monthly heatmap calendar --}}
<div class="card animate-in" style="animation-delay:.18s">
    <div class="card-title" style="margin-bottom:14px"><i class="fas fa-calendar-days" style="margin-right:5px"></i> Kalender Pengeluaran — {{ $date->translatedFormat('F Y') }}</div>

    <div style="display:grid;grid-template-columns:repeat(7,1fr);gap:4px;text-align:center">
        @foreach(['Sen','Sel','Rab','Kam','Jum','Sab','Min'] as $d)
            <div style="font-size:10px;font-weight:600;color:var(--text-muted);padding:4px 0;letter-spacing:.04em">{{ $d }}</div>
        @endforeach

        @php
            $firstDayOfMonth = $monthStart->copy()->dayOfWeekIso; // 1=Mon
            // Pad before first day
            for ($p = 1; $p < $firstDayOfMonth; $p++) {
                echo '<div></div>';
            }

            $daysInMonth = $monthEnd->day;
            for ($day = 1; $day <= $daysInMonth; $day++) {
                $dayDate = $monthStart->copy()->addDays($day - 1)->toDateString();
                $amount  = $dailyTotals[$dayDate] ?? 0;
                $isToday = $dayDate === today()->toDateString();
                $isSelected = $dayDate === $date->toDateString();

                $intensity = 0;
                if ($maxDaily > 0 && $amount > 0) {
                    $intensity = min(1, $amount / $maxDaily);
                }

                $bg = $amount > 0
                    ? 'rgba(239,68,68,' . round(0.15 + $intensity * 0.6, 2) . ')'
                    : 'var(--bg-hover)';

                $border = $isSelected ? '2px solid #8b5cf6' : ($isToday ? '2px solid #3b82f6' : '1px solid transparent');
                $fw = $isSelected || $isToday ? '700' : '400';
        @endphp
        <a href="?date={{ $dayDate }}"
           style="padding:6px 2px;border-radius:6px;font-size:12px;font-weight:{{ $fw }};
                  background:{{ $bg }};border:{{ $border }};color:{{ $amount > 0 ? 'var(--text-primary)' : 'var(--text-muted)' }};
                  text-decoration:none;display:block;transition:opacity .15s"
           title="{{ $amount > 0 ? 'Rp '.number_format($amount,0,',','.') : 'Tidak ada transaksi' }}">
            {{ $day }}
        </a>
        @php } @endphp
    </div>

    <div style="display:flex;align-items:center;gap:12px;margin-top:12px;font-size:11px;color:var(--text-muted)">
        <span>Rendah</span>
        @foreach([.15,.35,.55,.75,.95] as $op)
        <div style="width:14px;height:14px;border-radius:3px;background:rgba(239,68,68,{{ $op }})"></div>
        @endforeach
        <span>Tinggi</span>
        <div style="margin-left:auto;display:flex;gap:8px">
            <span style="display:flex;align-items:center;gap:4px"><span style="width:10px;height:10px;border-radius:2px;background:transparent;border:2px solid #3b82f6;display:inline-block"></span> Hari Ini</span>
            <span style="display:flex;align-items:center;gap:4px"><span style="width:10px;height:10px;border-radius:2px;background:transparent;border:2px solid #8b5cf6;display:inline-block"></span> Dipilih</span>
        </div>
    </div>
</div>

@endsection
