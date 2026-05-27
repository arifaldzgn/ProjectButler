<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="theme-color" content="#0a0a0a">
    <title>@yield('title', 'Butler Dashboard')</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <script defer src="https://unpkg.com/alpinejs@3.x.x/dist/cdn.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
    <style>
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

        :root {
            /* Background */
            --bg:          #0a0a0a;
            --bg-card:     #141414;
            --bg-elevated: #1c1c1c;
            /* Borders */
            --border:       rgba(255,255,255,0.07);
            --border-focus: rgba(255,255,255,0.18);
            /* Text */
            --text:         #f0f0f0;
            --text-primary: #f0f0f0;
            --text-secondary: #a0a0a0;
            --text-muted:   #666;
            --text-dim:     #444;
            /* Semantic colours */
            --red:     #ef4444;
            --green:   #22c55e;
            --orange:  #f97316;
            --blue:    #3b82f6;
            --purple:  #8b5cf6;
            --yellow:  #eab308;
            --accent:  #a78bfa;
            /* Aliases kept for backward compat */
            --success:  #22c55e;
            --warning:  #f97316;
            --err:      #ef4444;
            /* Layout */
            --radius:    14px;
            --radius-sm: 9px;
        }

        html, body {
            background: var(--bg);
            color: var(--text);
            font-family: 'Inter', system-ui, sans-serif;
            font-size: 14px;
            line-height: 1.6;
            min-height: 100dvh;
            -webkit-font-smoothing: antialiased;
        }

        /* ── Layout ─────────────────────────────────────────────── */
        .layout { max-width: 760px; margin: 0 auto; padding: 0 20px; }

        /* ── Nav ─────────────────────────────────────────────────── */
        nav {
            position: sticky; top: 0; z-index: 50;
            background: rgba(10,10,10,0.9);
            backdrop-filter: blur(16px);
            border-bottom: 1px solid var(--border);
        }
        .nav-inner {
            display: flex; align-items: center;
            justify-content: space-between;
            padding: 0 20px;
            max-width: 760px; margin: 0 auto;
            height: 54px;
        }
        .nav-brand {
            font-size: 13px; font-weight: 700;
            letter-spacing: 0.1em; text-transform: uppercase;
            color: var(--text);
        }
        .nav-links { display: flex; gap: 2px; overflow-x: auto; -webkit-overflow-scrolling: touch; }
        .nav-links::-webkit-scrollbar { display: none; }
        .nav-link {
            padding: 6px 12px; border-radius: var(--radius-sm);
            font-size: 13px; font-weight: 500;
            color: var(--text-muted); text-decoration: none;
            white-space: nowrap;
            transition: background .15s, color .15s;
        }
        .nav-link:hover { background: var(--bg-card); color: var(--text-secondary); }
        .nav-link.active { background: var(--bg-elevated); color: var(--text); }

        /* ── Admin Banner ────────────────────────────────────────── */
        .admin-banner {
            background: var(--yellow); color: #000;
            padding: 10px 20px;
            display: flex; justify-content: space-between; align-items: center;
            font-size: 13px; font-weight: 600;
        }
        .admin-banner form { margin: 0; }
        .admin-banner button {
            background: rgba(0,0,0,0.12); border: none;
            padding: 6px 12px; border-radius: 6px;
            cursor: pointer; font-weight: 600; color: #000;
        }

        /* ── Page Header ─────────────────────────────────────────── */
        .page-header { margin-bottom: 20px; }
        .page-header h2 { font-size: 20px; font-weight: 700; letter-spacing: -.02em; }
        .page-header p  { color: var(--text-muted); font-size: 13px; margin-top: 2px; }

        /* ── Cards & Grids ───────────────────────────────────────── */
        .card {
            background: var(--bg-card);
            border: 1px solid var(--border);
            border-radius: var(--radius);
            padding: 18px;
            margin-bottom: 14px;
        }
        .card-title {
            font-size: 12px; font-weight: 600;
            letter-spacing: .06em; text-transform: uppercase;
            color: var(--text-muted); margin-bottom: 12px;
        }
        .card-value {
            font-size: 26px; font-weight: 700;
            letter-spacing: -.03em; line-height: 1.1;
        }
        .card-subtitle { font-size: 12px; color: var(--text-muted); margin-top: 4px; }

        .stat-card { position: relative; overflow: hidden; }
        .stat-icon {
            font-size: 26px; margin-bottom: 10px;
            display: block; line-height: 1;
        }
        .stat-card.red    { border-color: rgba(239,68,68,.18);   background: rgba(239,68,68,.04); }
        .stat-card.green  { border-color: rgba(34,197,94,.18);   background: rgba(34,197,94,.04); }
        .stat-card.orange { border-color: rgba(249,115,22,.18);  background: rgba(249,115,22,.04); }
        .stat-card.blue   { border-color: rgba(59,130,246,.18);  background: rgba(59,130,246,.04); }
        .stat-card.accent { border-color: rgba(139,92,246,.18);  background: rgba(139,92,246,.04); }
        .stat-card.yellow { border-color: rgba(234,179,8,.18);   background: rgba(234,179,8,.04); }

        .grid-2 { display: grid; grid-template-columns: 1fr 1fr;     gap: 12px; margin-bottom: 14px; }
        .grid-3 { display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 12px; margin-bottom: 14px; }
        .grid-4 { display: grid; grid-template-columns: 1fr 1fr;     gap: 12px; margin-bottom: 14px; }

        @media (min-width: 580px) {
            .grid-4 { grid-template-columns: repeat(4, 1fr); }
            .grid-3 { grid-template-columns: repeat(3, 1fr); }
        }

        /* ── Charts ─────────────────────────────────────────────── */
        .chart-container { position: relative; height: 200px; width: 100%; }
        .chart-container canvas { width: 100% !important; }

        /* ── Progress bars ───────────────────────────────────────── */
        .progress-bar {
            height: 6px; background: rgba(255,255,255,.07);
            border-radius: 4px; overflow: hidden; margin-top: 8px;
        }
        .progress-fill { height: 100%; border-radius: 4px; transition: width .4s; }
        .progress-fill.green  { background: var(--green); }
        .progress-fill.red    { background: var(--red); }
        .progress-fill.orange { background: var(--orange); }
        .progress-fill.blue   { background: var(--blue); }
        .progress-fill.accent { background: var(--accent); }

        /* (default white fill for existing code) */
        .progress-track { height: 6px; background: rgba(255,255,255,.08); border-radius: 4px; overflow: hidden; margin-top: 8px; }
        .progress-track .progress-fill { background: var(--text); }

        /* ── Tables ─────────────────────────────────────────────── */
        .table-wrap {
            background: var(--bg-card); border: 1px solid var(--border);
            border-radius: var(--radius); overflow: hidden; margin-bottom: 14px;
        }
        .data-table { width: 100%; border-collapse: collapse; font-size: 13px; }
        .data-table thead tr { border-bottom: 1px solid var(--border); }
        .data-table th {
            padding: 11px 14px; text-align: left;
            font-size: 11px; font-weight: 600;
            letter-spacing: .06em; text-transform: uppercase; color: var(--text-dim);
        }
        .data-table td {
            padding: 12px 14px;
            border-bottom: 1px solid var(--border);
            vertical-align: middle; color: var(--text-secondary);
        }
        .data-table tr:last-child td { border-bottom: none; }
        .data-table tr:hover td { background: rgba(255,255,255,.02); }
        /* legacy table styles */
        table { width: 100%; border-collapse: collapse; font-size: 13px; }
        thead tr { border-bottom: 1px solid var(--border); }
        th { padding: 12px 16px; text-align: left; font-size: 11px; font-weight: 600;
             letter-spacing: .06em; text-transform: uppercase; color: var(--text-dim); }
        td { padding: 13px 16px; border-bottom: 1px solid var(--border); vertical-align: middle; }
        tr:last-child td { border-bottom: none; }
        tr:hover td { background: rgba(255,255,255,.02); }

        /* ── Badges ─────────────────────────────────────────────── */
        .badge {
            display: inline-block; padding: 2px 8px;
            border-radius: 100px; font-size: 11px; font-weight: 600;
            letter-spacing: .04em; text-transform: capitalize;
        }
        .badge-expense  { background: rgba(239,68,68,.15);  color: #fca5a5; }
        .badge-meal     { background: rgba(249,115,22,.15);  color: #fdba74; }
        .badge-food     { background: rgba(249,115,22,.15);  color: #fdba74; }
        .badge-income   { background: rgba(34,197,94,.15);   color: #86efac; }
        .badge-saving   { background: rgba(99,102,241,.15);  color: #a5b4fc; }
        .badge-parse    { background: rgba(59,130,246,.15);  color: #93c5fd; }
        .badge-summary  { background: rgba(139,92,246,.15);  color: #c4b5fd; }
        .badge-transfer { background: rgba(234,179,8,.15);   color: #fde68a; }
        .badge-bill_payment  { background: rgba(239,68,68,.12);  color: #fca5a5; }
        .badge-debt_payment  { background: rgba(239,68,68,.12);  color: #fca5a5; }
        .badge-sinking_fund_deposit { background: rgba(99,102,241,.12); color: #a5b4fc; }

        /* ── Amount colours ──────────────────────────────────────── */
        .amount-expense { color: #fca5a5; font-weight: 600; }
        .amount-income  { color: #86efac; font-weight: 600; }

        /* ── Filter bar ─────────────────────────────────────────── */
        .filter-bar { display: flex; gap: 8px; flex-wrap: wrap; margin: 16px 0; }
        .filter-bar select,
        .filter-bar input[type="date"] {
            background: var(--bg-card); border: 1px solid var(--border);
            color: var(--text); border-radius: var(--radius-sm);
            padding: 8px 12px; font-family: inherit; font-size: 13px;
            outline: none; transition: border-color .15s; color-scheme: dark;
        }
        .filter-bar select:focus,
        .filter-bar input:focus { border-color: var(--border-focus); }

        /* ── Stat grid (legacy) ──────────────────────────────────── */
        .stat-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; margin: 20px 0; }
        .stat-card .stat-label {
            font-size: 11px; color: var(--text-dim);
            letter-spacing: .08em; text-transform: uppercase; margin-bottom: 8px;
        }
        .stat-card .stat-value { font-size: 22px; font-weight: 700; letter-spacing: -.02em; }
        .stat-card .stat-sub   { font-size: 12px; color: var(--text-muted); margin-top: 4px; }

        /* ── Account row ─────────────────────────────────────────── */
        .account-row {
            display: flex; align-items: center; justify-content: space-between;
            padding: 14px 16px; border-bottom: 1px solid var(--border);
        }
        .account-row:last-child { border-bottom: none; }
        .account-name    { font-weight: 500; }
        .account-type    { font-size: 12px; color: var(--text-dim); margin-top: 2px; }
        .account-balance { font-size: 15px; font-weight: 700; }
        .default-badge {
            font-size: 10px; background: rgba(255,255,255,.07);
            color: var(--text-muted); padding: 2px 7px;
            border-radius: 4px; margin-left: 6px;
        }

        /* ── Fund card ───────────────────────────────────────────── */
        .fund-card {
            display: flex; flex-direction: column; padding: 16px;
            background: var(--bg-card); border: 1px solid var(--border);
            border-radius: var(--radius); margin-bottom: 12px;
        }
        .fund-header  { display: flex; justify-content: space-between; align-items: flex-end; margin-bottom: 6px; }
        .fund-name    { font-weight: 600; font-size: 14px; }
        .fund-balance { font-size: 15px; font-weight: 700; }
        .fund-target  { font-size: 12px; color: var(--text-muted); margin-bottom: 8px; }

        /* ── Meal item ───────────────────────────────────────────── */
        .meal-item {
            display: flex; justify-content: space-between; align-items: center;
            padding: 12px 0; border-bottom: 1px solid var(--border);
        }
        .meal-item:last-child { border-bottom: none; }
        .meal-info h4 { font-size: 14px; font-weight: 500; margin-bottom: 2px; }
        .meal-info p  { font-size: 12px; color: var(--text-muted); }
        .meal-cal     { font-weight: 700; font-size: 15px; color: var(--orange); white-space: nowrap; margin-left: 12px; }

        /* ── Empty state ─────────────────────────────────────────── */
        .empty-state {
            text-align: center; padding: 40px 20px;
            color: var(--text-muted);
        }
        .empty-icon { font-size: 36px; margin-bottom: 10px; }
        .empty-state h3 { font-size: 15px; font-weight: 600; margin-bottom: 4px; color: var(--text-secondary); }
        .empty-state p  { font-size: 13px; }

        /* ── Section title ───────────────────────────────────────── */
        .section-title {
            font-size: 11px; font-weight: 600; letter-spacing: .08em;
            text-transform: uppercase; color: var(--text-dim);
            margin: 28px 0 12px;
        }

        /* ── Form inputs (for settings) ──────────────────────────── */
        .field { margin-bottom: 18px; }
        .field-label {
            display: block; font-size: 12px; font-weight: 500;
            color: var(--text-muted); margin-bottom: 6px; letter-spacing: .02em;
        }
        .field-input {
            width: 100%; padding: 11px 14px;
            background: var(--bg-elevated); border: 1px solid var(--border);
            border-radius: var(--radius-sm); color: var(--text);
            font-family: inherit; font-size: 14px;
            outline: none; transition: border-color .15s;
            -webkit-appearance: none; appearance: none; color-scheme: dark;
        }
        .field-input:focus { border-color: var(--border-focus); }
        select.field-input {
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='16' height='16' fill='%23666' viewBox='0 0 16 16'%3E%3Cpath d='M7.247 11.14 2.451 5.658C1.885 5.013 2.345 4 3.204 4h9.592a1 1 0 0 1 .753 1.659l-4.796 5.48a1 1 0 0 1-1.506 0z'/%3E%3C/svg%3E");
            background-repeat: no-repeat; background-position: right 12px center; padding-right: 36px;
        }
        .btn-save {
            display: inline-flex; align-items: center; gap: 6px;
            padding: 10px 20px; background: var(--text); color: var(--bg);
            border: none; border-radius: var(--radius-sm);
            font-family: inherit; font-size: 14px; font-weight: 600;
            cursor: pointer; transition: opacity .15s;
        }
        .btn-save:hover { opacity: .85; }
        .btn-danger {
            background: rgba(239,68,68,.12); color: var(--red);
            border: 1px solid rgba(239,68,68,.2);
            padding: 10px 20px; border-radius: var(--radius-sm);
            font-family: inherit; font-size: 14px; font-weight: 600;
            cursor: pointer; transition: background .15s;
        }
        .btn-danger:hover { background: rgba(239,68,68,.2); }

        /* ── Toggle ─────────────────────────────────────────────── */
        .toggle-row {
            display: flex; align-items: center; gap: 14px;
            padding: 14px; background: var(--bg-card);
            border: 1px solid var(--border); border-radius: var(--radius);
            cursor: pointer; transition: border-color .15s; margin-bottom: 10px;
        }
        .toggle-row:hover { border-color: var(--border-focus); }
        .toggle-visual {
            width: 40px; height: 24px; border-radius: 12px;
            background: var(--bg-elevated); border: 1px solid var(--border);
            position: relative; transition: background .2s; flex-shrink: 0;
        }
        .toggle-visual::after {
            content: ''; position: absolute;
            width: 18px; height: 18px; border-radius: 50%;
            background: var(--text-dim); top: 2px; left: 2px;
            transition: transform .2s, background .2s;
        }
        .toggle-row.is-on .toggle-visual { background: rgba(255,255,255,.1); border-color: var(--border-focus); }
        .toggle-row.is-on .toggle-visual::after { transform: translateX(16px); background: var(--text); }
        .toggle-label { font-size: 14px; font-weight: 500; }
        .toggle-desc  { font-size: 12px; color: var(--text-muted); margin-top: 2px; }

        /* ── Misc helpers ───────────────────────────────────────── */
        h2 { font-size: 20px; font-weight: 700; letter-spacing: -.02em; }
        .page-desc { color: var(--text-muted); font-size: 13px; margin-bottom: 16px; }
        .animate-in { animation: fadeUp .28s cubic-bezier(.16,1,.3,1) both; }
        @keyframes fadeUp { from { opacity:0; transform: translateY(8px); } to { opacity:1; transform: translateY(0); } }
        [x-cloak] { display: none !important; }

        .alert-success {
            padding: 12px 16px; background: rgba(34,197,94,.1);
            border: 1px solid rgba(34,197,94,.2); border-radius: var(--radius-sm);
            color: #86efac; font-size: 13px; margin-bottom: 16px;
        }
        .alert-error {
            padding: 12px 16px; background: rgba(239,68,68,.1);
            border: 1px solid rgba(239,68,68,.2); border-radius: var(--radius-sm);
            color: #fca5a5; font-size: 13px; margin-bottom: 16px;
        }

        /* Chart.js global defaults */
        .chart-legend { display: flex; gap: 16px; flex-wrap: wrap; margin-top: 10px; }
        .chart-legend-item { display: flex; align-items: center; gap: 6px; font-size: 12px; color: var(--text-muted); }
        .chart-legend-dot { width: 8px; height: 8px; border-radius: 50%; }

        /* Pagination */
        .pagination { display: flex; gap: 4px; justify-content: center; padding: 20px 0; flex-wrap: wrap; }
        .page-btn {
            padding: 7px 12px; border-radius: var(--radius-sm); font-size: 13px;
            color: var(--text-muted); text-decoration: none;
            border: 1px solid var(--border); transition: all .15s;
        }
        .page-btn:hover, .page-btn.active {
            background: var(--bg-card); color: var(--text); border-color: var(--border-focus);
        }
    </style>
</head>
<body>

@if(session()->has('admin_impersonator_id'))
<div class="admin-banner">
    <span>⚠️ Admin: Viewing {{ request()->dashboard_user?->name ?? 'User' }}</span>
    <form action="{{ route('admin.impersonate.leave') }}" method="POST">
        @csrf <button type="submit">← Back to Admin</button>
    </form>
</div>
@endif

<nav>
    <div class="nav-inner">
        <div class="nav-brand">🤖 Butler</div>
        <div class="nav-links">
            <a href="{{ route('dashboard.index') }}"
               class="nav-link {{ request()->routeIs('dashboard.index') ? 'active' : '' }}">Beranda</a>
            <a href="{{ route('dashboard.spending') }}"
               class="nav-link {{ request()->routeIs('dashboard.spending') ? 'active' : '' }}">Spending</a>
            @if(request()->dashboard_user?->isCalorieMode())
            <a href="{{ route('dashboard.nutrition') }}"
               class="nav-link {{ request()->routeIs('dashboard.nutrition') ? 'active' : '' }}">Nutrition</a>
            @endif
            <a href="{{ route('dashboard.insights') }}"
               class="nav-link {{ request()->routeIs('dashboard.insights') ? 'active' : '' }}">Insights</a>
            <a href="{{ route('dashboard.history') }}"
               class="nav-link {{ request()->routeIs('dashboard.history') ? 'active' : '' }}">Riwayat</a>
            <a href="{{ route('dashboard.settings') }}"
               class="nav-link {{ request()->routeIs('dashboard.settings') ? 'active' : '' }}">Settings</a>
            @if(request()->dashboard_user?->isAdmin() && !session()->has('admin_impersonator_id'))
            <a href="{{ route('admin.users.index') }}"
               class="nav-link {{ request()->routeIs('admin.*') ? 'active' : '' }}"
               style="color: var(--yellow)">Admin</a>
            @endif
        </div>
    </div>
</nav>

<main class="layout" style="padding-top: 28px; padding-bottom: 60px;">
    @if(session('success'))
        <div class="alert-success">✓ {{ session('success') }}</div>
    @endif
    @if(session('error'))
        <div class="alert-error">{{ session('error') }}</div>
    @endif
    @yield('content')
</main>

<script>
// Global Chart.js defaults for dark theme
Chart.defaults.color = '#666';
Chart.defaults.borderColor = 'rgba(255,255,255,0.06)';
Chart.defaults.font.family = "'Inter', system-ui, sans-serif";
Chart.defaults.font.size = 12;

function formatRupiah(v) {
    if (v >= 1000000) return 'Rp ' + (v/1000000).toFixed(1) + 'jt';
    if (v >= 1000)    return 'Rp ' + (v/1000).toFixed(0) + 'k';
    return 'Rp ' + v;
}
</script>

@yield('scripts')
</body>
</html>
