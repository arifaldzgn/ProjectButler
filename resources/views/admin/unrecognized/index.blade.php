@extends('layouts.dashboard')
@section('title', 'Unrecognized Messages')

@section('content')
<div class="page-header animate-in">
    <div>
        <h2>Unrecognized Messages</h2>
        <p>Phrases Butler's parser couldn't confidently route — grouped & ranked by frequency.</p>
    </div>
</div>

@include('admin.partials.nav')

{{-- ── Stats ────────────────────────────────────────────────── --}}
<div class="grid-3 animate-in" style="animation-delay:.04s">
    <div class="card stat-card red">
        <div class="stat-icon"><i class="fas fa-circle-question"></i></div>
        <div class="card-title">Unrecognized (7d)</div>
        <div class="card-value">{{ number_format($stats['total_7d']) }}</div>
        <div class="card-subtitle">total fallbacks fired</div>
    </div>
    <div class="card stat-card orange">
        <div class="stat-icon"><i class="fas fa-arrows-rotate"></i></div>
        <div class="card-title">Unique Phrases (7d)</div>
        <div class="card-value">{{ number_format($stats['unique_phrases_7d']) }}</div>
        <div class="card-subtitle">distinct messages</div>
    </div>
    <div class="card stat-card blue">
        <div class="stat-icon"><i class="fas fa-users"></i></div>
        <div class="card-title">Affected Users (7d)</div>
        <div class="card-value">{{ number_format($stats['unique_users_7d']) }}</div>
        <div class="card-subtitle">unique users</div>
    </div>
</div>

@if($stats['top_phrase'])
<div class="card animate-in" style="animation-delay:.07s;background:rgba(249,115,22,0.04);border-color:rgba(249,115,22,0.18)">
    <div style="display:flex;gap:14px;align-items:flex-start">
        <i class="fas fa-trophy" style="font-size:22px;color:var(--yellow);flex-shrink:0"></i>
        <div style="flex:1;min-width:0">
            <div style="font-size:12px;font-weight:600;text-transform:uppercase;letter-spacing:.06em;color:var(--text-muted);margin-bottom:4px">
                Top Unrecognized Phrase (7d)
            </div>
            <div style="font-size:18px;font-weight:700;color:var(--text-primary);font-family:monospace;
                        word-break:break-word">
                "{{ $stats['top_phrase'] }}"
            </div>
            <div style="font-size:12px;color:var(--text-muted);margin-top:4px">
                {{ $stats['top_phrase_count'] }} occurrences
            </div>
        </div>
    </div>
</div>
@endif

{{-- ── Filters ───────────────────────────────────────────────── --}}
<div class="card animate-in" style="animation-delay:.10s">
    <form method="GET" style="display:flex;gap:12px;flex-wrap:wrap;align-items:flex-end">
        <div>
            <label class="field-label">Dari Tanggal</label>
            <input type="date" name="from" value="{{ request('from', $from?->format('Y-m-d')) }}"
                   class="field-input" style="width:auto">
        </div>
        <div>
            <label class="field-label">Sampai Tanggal</label>
            <input type="date" name="to" value="{{ request('to', $to?->format('Y-m-d')) }}"
                   class="field-input" style="width:auto">
        </div>
        <div>
            <label class="field-label">Min Frequency</label>
            <input type="number" name="min_count" value="{{ $minCnt }}" min="1"
                   class="field-input" style="width:120px">
        </div>
        <button type="submit" class="btn-save" style="padding:11px 18px">Filter</button>
        @if(request()->hasAny(['from','to','min_count']))
        <a href="{{ route('admin.unrecognized.index') }}"
           style="font-size:13px;color:var(--text-muted);text-decoration:none;padding:11px 6px">Reset</a>
        @endif
        <a href="{{ request()->fullUrlWithQuery(['export' => 'csv']) }}"
           style="margin-left:auto;padding:10px 14px;border-radius:var(--radius-sm);border:1px solid var(--border);
                  background:var(--bg-card);font-size:12px;color:var(--text-secondary);
                  text-decoration:none;font-weight:500;white-space:nowrap">
            ⬇ Export CSV
        </a>
    </form>
</div>

{{-- ── Grouped phrases table ─────────────────────────────────── --}}
<div class="card animate-in" style="animation-delay:.12s;padding:0;overflow:hidden">
    @if($grouped->count() > 0)
    <div style="overflow-x:auto">
    <table class="data-table">
        <thead>
            <tr>
                <th style="min-width:220px">Phrase</th>
                <th style="text-align:right;white-space:nowrap">Occurrences</th>
                <th style="text-align:right;white-space:nowrap">Users</th>
                <th style="white-space:nowrap">Last Seen</th>
                <th style="min-width:200px">Suggestion Sent</th>
            </tr>
        </thead>
        <tbody>
            @foreach($grouped as $row)
            @php
                $payload = json_decode($row->sample_suggestion ?? '{}', true);
                $sugg    = $payload['suggestion'] ?? null;
                $reason  = $payload['reason'] ?? null;
            @endphp
            <tr>
                <td style="font-family:monospace;color:var(--text-primary);word-break:break-word;max-width:340px">
                    "{{ $row->phrase }}"
                    @if($reason)
                        <div style="margin-top:4px">
                            <span style="font-size:10px;padding:2px 7px;border-radius:999px;
                                        background:var(--bg-hover);color:var(--text-muted);
                                        font-family:'Inter',sans-serif;text-transform:lowercase;letter-spacing:.02em">
                                {{ str_replace('_',' ',$reason) }}
                            </span>
                        </div>
                    @endif
                </td>
                <td style="text-align:right;font-weight:700;font-size:15px;color:var(--text-primary)">
                    {{ $row->occurrences }}
                </td>
                <td style="text-align:right;color:var(--text-muted)">{{ $row->unique_users }}</td>
                <td style="white-space:nowrap;color:var(--text-muted);font-size:12px">
                    {{ \Carbon\Carbon::parse($row->last_seen)->diffForHumans() }}
                </td>
                <td>
                    @if($sugg && !empty($sugg['label']))
                        <div style="display:flex;flex-direction:column;gap:3px">
                            <span class="badge" style="
                                background:{{ $sugg['kind'] === 'unsupported' ? 'rgba(239,68,68,0.12)' : 'rgba(139,92,246,0.12)' }};
                                color:{{ $sugg['kind'] === 'unsupported' ? 'var(--red)' : 'var(--accent)' }};
                                width:fit-content
                            ">
                                {{ $sugg['kind'] === 'unsupported' ? 'unsupported' : ($sugg['kind'] === 'unclear' ? 'unclear' : 'did you mean') }}
                            </span>
                            <span style="font-size:12px;color:var(--text-secondary)">
                                {{ $sugg['label'] }}
                                @if(!empty($sugg['stage']))
                                    <span style="color:var(--text-dim);font-size:10px">· stage {{ $sugg['stage'] }}</span>
                                @endif
                            </span>
                        </div>
                    @else
                        <span style="font-size:12px;color:var(--text-dim);font-style:italic">no match</span>
                    @endif
                </td>
            </tr>
            @endforeach
        </tbody>
    </table>
    </div>

    {{-- Pagination --}}
    @if($grouped->hasPages())
    <div style="padding:14px;border-top:1px solid var(--border)">
        {{ $grouped->links() }}
    </div>
    @endif

    @else
    <div class="empty-state">
        <div class="empty-icon"><i class="fas fa-circle-check"></i></div>
        <h3>Tidak ada pesan yang gagal dikenali</h3>
        <p>Butler memahami semua input pada rentang waktu ini.</p>
    </div>
    @endif
</div>

@endsection
