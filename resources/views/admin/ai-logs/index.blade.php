@extends('layouts.dashboard')
@section('title', 'AI Logs')

@section('content')
<div class="page-header animate-in">
    <div>
        <h2>AI Logs</h2>
        <p>Parse calls, summary calls, latency, confidence, and failures</p>
    </div>
</div>

@include('admin.partials.nav')

<!-- Stats -->
<div class="grid-4">
    <div class="card stat-card blue animate-in">
        <div class="stat-icon"><i class="fas fa-chart-bar"></i></div>
        <div class="card-title">Calls Today</div>
        <div class="card-value">{{ number_format($stats['total_today']) }}</div>
        <div class="card-subtitle">AI requests</div>
    </div>
    <div class="card stat-card red animate-in">
        <div class="stat-icon"><i class="fas fa-circle-xmark"></i></div>
        <div class="card-title">Failures Today</div>
        <div class="card-value">{{ $stats['failures_today'] }}</div>
        <div class="card-subtitle">unsuccessful calls</div>
    </div>
    <div class="card stat-card orange animate-in">
        <div class="stat-icon"><i class="fas fa-stopwatch"></i></div>
        <div class="card-title">Avg Latency</div>
        <div class="card-value">{{ $stats['avg_latency_ms'] }}ms</div>
        <div class="card-subtitle">today</div>
    </div>
    <div class="card stat-card green animate-in">
        <div class="stat-icon"><i class="fas fa-bullseye"></i></div>
        <div class="card-title">Avg Confidence</div>
        <div class="card-value">{{ $stats['avg_confidence'] }}</div>
        <div class="card-subtitle">today</div>
    </div>
</div>

<!-- Intent Breakdown (last 7 days) -->
@if($intentBreakdown->isNotEmpty())
<div class="card animate-in" style="padding:0;overflow:hidden;margin-bottom:16px">
    <div style="padding:12px 16px;border-bottom:1px solid var(--border);display:flex;align-items:center;justify-content:space-between">
        <div class="card-title" style="margin:0">Intent Breakdown (7 hari terakhir, parse calls)</div>
        <span style="font-size:11px;color:var(--text-dim)">Failure = parse throw / confidence &lt; 0.50 / unknown</span>
    </div>
    <div style="overflow-x:auto">
    <table class="data-table">
        <thead>
            <tr>
                <th>Intent</th>
                <th style="text-align:right">Total</th>
                <th style="text-align:right">Failures</th>
                <th style="text-align:right">Failure %</th>
                <th style="text-align:right">Avg Conf.</th>
                <th style="text-align:right">Avg Latency</th>
            </tr>
        </thead>
        <tbody>
            @foreach($intentBreakdown as $row)
            @php
                $failPct  = $row->total > 0 ? round($row->failures / $row->total * 100) : 0;
                $failColor = $failPct >= 30 ? 'var(--red)' : ($failPct >= 10 ? 'var(--orange)' : 'var(--green)');
            @endphp
            <tr>
                <td style="font-family:monospace;font-size:12px;color:var(--text-primary)">{{ $row->intent }}</td>
                <td style="text-align:right;font-weight:600">{{ $row->total }}</td>
                <td style="text-align:right;color:var(--red)">{{ $row->failures }}</td>
                <td style="text-align:right">
                    <span style="color:{{ $failColor }};font-weight:600">{{ $failPct }}%</span>
                </td>
                <td style="text-align:right;color:{{ ($row->avg_conf ?? 1) >= 0.8 ? 'var(--green)' : (($row->avg_conf ?? 1) >= 0.5 ? 'var(--orange)' : 'var(--red)') }}">
                    {{ $row->avg_conf !== null ? number_format($row->avg_conf, 2) : '—' }}
                </td>
                <td style="text-align:right;color:var(--text-muted);font-size:12px">{{ $row->avg_latency ? $row->avg_latency . 'ms' : '—' }}</td>
            </tr>
            @endforeach
        </tbody>
    </table>
    </div>
</div>
@endif

<!-- Filters -->
<div class="card animate-in" style="margin-bottom: 16px;">
    <form method="GET" style="display: flex; gap: 12px; flex-wrap: wrap; align-items: flex-end;">
        <div>
            <label style="font-size: 12px; color: var(--text-muted); display: block; margin-bottom: 4px;">Call Type</label>
            <select name="call_type" style="background: var(--surface); border: 1px solid rgba(108,92,231,0.2); color: var(--text-primary); padding: 6px 10px; border-radius: 6px;">
                <option value="">All</option>
                @foreach($callTypes as $ct)
                    <option value="{{ $ct }}" {{ request('call_type') === $ct ? 'selected' : '' }}>{{ $ct }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label style="font-size: 12px; color: var(--text-muted); display: block; margin-bottom: 4px;">Status</label>
            <select name="success" style="background: var(--surface); border: 1px solid rgba(108,92,231,0.2); color: var(--text-primary); padding: 6px 10px; border-radius: 6px;">
                <option value="">All</option>
                <option value="1" {{ request('success') === '1' ? 'selected' : '' }}>Success</option>
                <option value="0" {{ request('success') === '0' ? 'selected' : '' }}>Failed</option>
            </select>
        </div>
        <div>
            <label style="font-size: 12px; color: var(--text-muted); display: block; margin-bottom: 4px;">User</label>
            <select name="user_id" style="background: var(--surface); border: 1px solid rgba(108,92,231,0.2); color: var(--text-primary); padding: 6px 10px; border-radius: 6px;">
                <option value="">All Users</option>
                @foreach($users as $u)
                    <option value="{{ $u->id }}" {{ request('user_id') == $u->id ? 'selected' : '' }}>{{ $u->name }}</option>
                @endforeach
            </select>
        </div>
        <button type="submit" style="background: var(--accent); color: white; border: none; padding: 8px 16px; border-radius: 6px; cursor: pointer;">Filter</button>
        <a href="{{ route('admin.ai-logs.index') }}" style="color: var(--text-muted); padding: 8px 12px; font-size: 13px;">Clear</a>
    </form>
</div>

<!-- Logs Table -->
<div class="card animate-in">
    <div class="card-title">Recent Calls ({{ $logs->total() }} total)</div>
    <table class="data-table">
        <thead>
            <tr>
                <th>Time</th>
                <th>User</th>
                <th>Type</th>
                <th>Intent</th>
                <th>Conf.</th>
                <th>Latency</th>
                <th>Status</th>
                <th>Input</th>
                <th>Error</th>
            </tr>
        </thead>
        <tbody>
            @forelse($logs as $log)
            <tr style="{{ !$log->was_successful ? 'background: rgba(255,107,129,0.05);' : '' }}">
                <td style="white-space: nowrap; font-size: 12px;">{{ $log->created_at->format('d M H:i:s') }}</td>
                <td>{{ $log->user?->name ?? '—' }}</td>
                <td><span class="badge badge-{{ $log->call_type }}">{{ $log->call_type }}</span></td>
                <td style="font-size: 12px; color: var(--text-secondary);">{{ $log->intent_detected ?? '—' }}</td>
                <td style="font-size: 12px;">
                    @if($log->confidence_score)
                        <span style="color: {{ $log->confidence_score >= 0.8 ? 'var(--green)' : ($log->confidence_score >= 0.5 ? 'var(--orange)' : 'var(--red)') }}">
                            {{ number_format($log->confidence_score, 2) }}
                        </span>
                    @else
                        —
                    @endif
                </td>
                <td style="font-size: 12px; color: {{ $log->latency_ms > 3000 ? 'var(--red)' : 'var(--text-secondary)' }};">
                    {{ $log->latency_ms ? $log->latency_ms . 'ms' : '—' }}
                </td>
                <td>
                    @if($log->was_successful)
                        <i class="fas fa-check" style="color:var(--green);font-size:11px"></i>
                    @else
                        <i class="fas fa-xmark" style="color:var(--red);font-size:11px"></i>
                    @endif
                </td>
                <td style="max-width: 200px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; font-size: 11px; color: var(--text-muted);" title="{{ $log->raw_input }}">
                    {{ Str::limit($log->raw_input, 60) }}
                </td>
                <td style="max-width: 160px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; font-size: 11px; color: var(--red);" title="{{ $log->error_message }}">
                    {{ $log->error_message ? Str::limit($log->error_message, 50) : '' }}
                </td>
            </tr>
            @empty
            <tr>
                <td colspan="9" style="text-align: center; color: var(--text-muted); padding: 32px;">No AI logs found.</td>
            </tr>
            @endforelse
        </tbody>
    </table>
    <div style="margin-top: 16px;">{{ $logs->links() }}</div>
</div>
@endsection
