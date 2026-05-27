@extends('layouts.dashboard')
@section('title', 'AI Logs')

@section('content')
<div class="page-header">
    <h2>AI Logs 🤖</h2>
    <p>Parse calls, summary calls, latency, confidence, and failures</p>
</div>

<!-- Stats -->
<div class="grid-4">
    <div class="card stat-card blue animate-in">
        <div class="stat-icon">📊</div>
        <div class="card-title">Calls Today</div>
        <div class="card-value">{{ number_format($stats['total_today']) }}</div>
        <div class="card-subtitle">AI requests</div>
    </div>
    <div class="card stat-card red animate-in">
        <div class="stat-icon">❌</div>
        <div class="card-title">Failures Today</div>
        <div class="card-value">{{ $stats['failures_today'] }}</div>
        <div class="card-subtitle">unsuccessful calls</div>
    </div>
    <div class="card stat-card orange animate-in">
        <div class="stat-icon">⏱️</div>
        <div class="card-title">Avg Latency</div>
        <div class="card-value">{{ $stats['avg_latency_ms'] }}ms</div>
        <div class="card-subtitle">today</div>
    </div>
    <div class="card stat-card green animate-in">
        <div class="stat-icon">🎯</div>
        <div class="card-title">Avg Confidence</div>
        <div class="card-value">{{ $stats['avg_confidence'] }}</div>
        <div class="card-subtitle">today</div>
    </div>
</div>

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
                        <span style="color: var(--green); font-size: 12px;">✓</span>
                    @else
                        <span style="color: var(--red); font-size: 12px;">✗</span>
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
