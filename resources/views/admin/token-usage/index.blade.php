@extends('layouts.dashboard')
@section('title', 'Token Usage — Admin')
@section('content')

<div class="page-header animate-in">
    <div>
        <h2>Token Usage 🔢</h2>
        <p>Konsumsi token AI per user dan per tipe panggilan.</p>
    </div>
</div>

@include('admin.partials.nav')

{{-- Period filter --}}
<div class="animate-in" style="animation-delay:.05s;margin-bottom:20px">
    <div style="display:flex;gap:8px;flex-wrap:wrap;align-items:center">
        <span style="font-size:13px;color:var(--text-secondary)">Periode:</span>
        @foreach(['7d' => '7 Hari', '30d' => '30 Hari', 'all' => 'Semua'] as $key => $label)
            <a href="?period={{ $key }}"
               style="padding:6px 14px;border-radius:var(--radius-sm);font-size:13px;font-weight:500;
                      text-decoration:none;border:1px solid {{ $period === $key ? 'var(--accent)' : 'var(--border)' }};
                      background:{{ $period === $key ? 'rgba(139,92,246,0.10)' : 'var(--bg-card)' }};
                      color:{{ $period === $key ? 'var(--accent)' : 'var(--text-secondary)' }}">
                {{ $label }}
            </a>
        @endforeach
    </div>
</div>

{{-- Summary cards --}}
<div class="animate-in" style="animation-delay:.1s;display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:12px;margin-bottom:24px">
    <div class="stat-card">
        <div class="stat-label">Total Token</div>
        <div class="stat-value">{{ number_format($systemTotals->total_tokens) }}</div>
    </div>
    <div class="stat-card">
        <div class="stat-label">Input Token</div>
        <div class="stat-value">{{ number_format($systemTotals->total_input) }}</div>
    </div>
    <div class="stat-card">
        <div class="stat-label">Output Token</div>
        <div class="stat-value">{{ number_format($systemTotals->total_output) }}</div>
    </div>
    <div class="stat-card">
        <div class="stat-label">Total Panggilan</div>
        <div class="stat-value">{{ number_format($systemTotals->total_calls) }}</div>
    </div>
    <div class="stat-card">
        <div class="stat-label">Estimasi Biaya</div>
        <div class="stat-value">${{ number_format($estimatedCost, 4) }}</div>
    </div>
    <div class="stat-card">
        <div class="stat-label">Data Terisi</div>
        <div class="stat-value">{{ $populatedPct }}%</div>
    </div>
</div>

{{-- By call type --}}
<div class="animate-in" style="animation-delay:.15s;margin-bottom:24px">
    <h3 style="font-size:15px;font-weight:600;margin-bottom:12px;color:var(--text-primary)">Per Tipe Panggilan</h3>
    <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th>Tipe</th>
                    <th style="text-align:right">Panggilan</th>
                    <th style="text-align:right">Input</th>
                    <th style="text-align:right">Output</th>
                    <th style="text-align:right">Total</th>
                </tr>
            </thead>
            <tbody>
                @forelse($byCallType as $row)
                <tr>
                    <td>
                        <span style="display:inline-block;padding:2px 8px;border-radius:4px;font-size:12px;font-weight:500;
                              background:{{ $row->call_type === 'parse' ? 'rgba(59,130,246,0.15)' : ($row->call_type === 'summary' ? 'rgba(168,85,247,0.15)' : 'rgba(107,114,128,0.15)') }};
                              color:{{ $row->call_type === 'parse' ? '#93c5fd' : ($row->call_type === 'summary' ? '#c4b5fd' : 'var(--text-secondary)') }}">
                            {{ $row->call_type }}
                        </span>
                    </td>
                    <td style="text-align:right;font-variant-numeric:tabular-nums">{{ number_format($row->total_calls) }}</td>
                    <td style="text-align:right;font-variant-numeric:tabular-nums">{{ number_format($row->total_input) }}</td>
                    <td style="text-align:right;font-variant-numeric:tabular-nums">{{ number_format($row->total_output) }}</td>
                    <td style="text-align:right;font-weight:600;font-variant-numeric:tabular-nums">{{ number_format($row->total_tokens) }}</td>
                </tr>
                @empty
                <tr><td colspan="5" style="text-align:center;color:var(--text-secondary)">Belum ada data</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

{{-- Per user --}}
<div class="animate-in" style="animation-delay:.2s">
    <h3 style="font-size:15px;font-weight:600;margin-bottom:12px;color:var(--text-primary)">Per User</h3>
    <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th>User</th>
                    <th style="text-align:right">Panggilan</th>
                    <th style="text-align:right">Input</th>
                    <th style="text-align:right">Output</th>
                    <th style="text-align:right">Total</th>
                    <th style="text-align:right">Avg/Call</th>
                </tr>
            </thead>
            <tbody>
                @forelse($perUser as $row)
                <tr>
                    <td style="font-weight:500">{{ $row->user_name }}</td>
                    <td style="text-align:right;font-variant-numeric:tabular-nums">{{ number_format($row->total_calls) }}</td>
                    <td style="text-align:right;font-variant-numeric:tabular-nums">{{ number_format($row->total_input) }}</td>
                    <td style="text-align:right;font-variant-numeric:tabular-nums">{{ number_format($row->total_output) }}</td>
                    <td style="text-align:right;font-weight:600;font-variant-numeric:tabular-nums">{{ number_format($row->total_tokens) }}</td>
                    <td style="text-align:right;font-variant-numeric:tabular-nums;color:var(--text-secondary)">{{ number_format($row->avg_per_call) }}</td>
                </tr>
                @empty
                <tr><td colspan="6" style="text-align:center;color:var(--text-secondary)">Belum ada data</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

@if($populatedPct < 100 && $populatedPct > 0)
<div class="animate-in" style="animation-delay:.25s;margin-top:16px;padding:12px 16px;background:rgba(234,179,8,0.10);border:1px solid rgba(234,179,8,0.25);border-radius:var(--radius-sm);font-size:13px;color:var(--text-secondary)">
    <strong style="color:#fbbf24">Info:</strong> {{ $populatedPct }}% dari log AI memiliki data token. Log lama (sebelum fitur ini aktif) tidak memiliki data token.
</div>
@endif

@endsection
