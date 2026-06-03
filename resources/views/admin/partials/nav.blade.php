@php
    $adminNav = [
        [
            'route'   => 'admin.users.index',
            'fa'      => 'fa-users',
            'label'   => 'Users',
            'desc'    => 'Semua pengguna',
        ],
        [
            'route'   => 'admin.ai-logs.index',
            'fa'      => 'fa-robot',
            'label'   => 'AI Logs',
            'desc'    => 'Parse & latency',
        ],
        [
            'route'   => 'admin.unrecognized.index',
            'fa'      => 'fa-circle-question',
            'label'   => 'Unrecognized',
            'desc'    => 'Pesan tak dikenali',
        ],
        [
            'route'   => 'admin.token-usage.index',
            'fa'      => 'fa-hashtag',
            'label'   => 'Token Usage',
            'desc'    => 'Konsumsi token AI',
        ],
    ];
@endphp

<div class="animate-in" style="margin-bottom:20px">
    <div style="display:flex;gap:8px;flex-wrap:wrap">
        @foreach($adminNav as $nav)
        @php $isActive = request()->routeIs($nav['route']); @endphp
        <a href="{{ route($nav['route']) }}"
           style="display:flex;align-items:center;gap:8px;padding:9px 14px;
                  border-radius:var(--radius-sm);border:1px solid {{ $isActive ? 'var(--accent)' : 'var(--border)' }};
                  background:{{ $isActive ? 'rgba(139,92,246,0.10)' : 'var(--bg-card)' }};
                  color:{{ $isActive ? 'var(--accent)' : 'var(--text-secondary)' }};
                  text-decoration:none;font-size:13px;font-weight:{{ $isActive ? '600' : '500' }};
                  box-shadow:var(--card-shadow);transition:border-color .2s,background .2s,color .2s;">
            <i class="fas {{ $nav['fa'] }}" style="font-size:14px;width:16px;text-align:center"></i>
            <span>{{ $nav['label'] }}</span>
            @if($isActive)
                <span style="width:6px;height:6px;border-radius:50%;background:var(--accent);display:inline-block"></span>
            @endif
        </a>
        @endforeach
    </div>
</div>
