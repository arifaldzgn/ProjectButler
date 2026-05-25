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
    <style>
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
        :root {
            --bg: #0a0a0a; --bg-card: #141414; --bg-elevated: #1c1c1c;
            --border: rgba(255,255,255,0.08); --border-focus: rgba(255,255,255,0.18);
            --text: #f5f5f5; --text-muted: #888; --text-dim: #555;
            --accent: #ffffff; --success: #22c55e; --warning: #f59e0b; --err: #ef4444;
            --radius: 12px; --radius-sm: 8px;
        }
        html, body { background: var(--bg); color: var(--text); font-family: 'Inter', system-ui, sans-serif;
            font-size: 14px; line-height: 1.6; min-height: 100dvh; -webkit-font-smoothing: antialiased; }
        .layout { max-width: 720px; margin: 0 auto; padding: 0 20px; }
        nav {
            position: sticky; top: 0; z-index: 50;
            background: rgba(10,10,10,0.85); backdrop-filter: blur(12px);
            border-bottom: 1px solid var(--border);
        }
        .nav-inner { display: flex; align-items: center; justify-content: space-between;
            padding: 16px 20px; max-width: 720px; margin: 0 auto; }
        .nav-brand { font-size: 13px; font-weight: 700; letter-spacing: 0.08em; text-transform: uppercase; }
        .nav-links { display: flex; gap: 4px; }
        .nav-link { padding: 7px 14px; border-radius: var(--radius-sm); font-size: 13px;
            color: var(--text-muted); text-decoration: none; transition: background 0.15s, color 0.15s; }
        .nav-link:hover, .nav-link.active { background: var(--bg-card); color: var(--text); }
        .stat-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; margin: 24px 0; }
        .stat-card { background: var(--bg-card); border: 1px solid var(--border);
            border-radius: var(--radius); padding: 20px; }
        .stat-label { font-size: 11px; color: var(--text-dim); letter-spacing: 0.08em;
            text-transform: uppercase; margin-bottom: 8px; }
        .stat-value { font-size: 22px; font-weight: 700; letter-spacing: -0.02em; }
        .stat-sub { font-size: 12px; color: var(--text-muted); margin-top: 4px; }
        .table-wrap { background: var(--bg-card); border: 1px solid var(--border);
            border-radius: var(--radius); overflow: hidden; }
        table { width: 100%; border-collapse: collapse; font-size: 13px; }
        thead tr { border-bottom: 1px solid var(--border); }
        th { padding: 12px 16px; text-align: left; font-size: 11px; font-weight: 600;
            letter-spacing: 0.06em; text-transform: uppercase; color: var(--text-dim); }
        td { padding: 13px 16px; border-bottom: 1px solid var(--border); vertical-align: middle; }
        tr:last-child td { border-bottom: none; }
        tr:hover td { background: rgba(255,255,255,0.02); }
        .badge { display: inline-block; padding: 3px 9px; border-radius: 100px; font-size: 11px;
            font-weight: 600; letter-spacing: 0.04em; text-transform: capitalize; }
        .badge-expense { background: rgba(239,68,68,0.15); color: #fca5a5; }
        .badge-meal    { background: rgba(34,197,94,0.15);  color: #86efac; }
        .badge-income  { background: rgba(16,185,129,0.15); color: #6ee7b7; }
        .badge-saving  { background: rgba(99,102,241,0.15); color: #a5b4fc; }
        .amount-expense { color: #fca5a5; font-weight: 600; }
        .amount-income  { color: #86efac; font-weight: 600; }
        .filter-bar { display: flex; gap: 8px; flex-wrap: wrap; margin: 20px 0; }
        .filter-bar select, .filter-bar input[type="date"] {
            background: var(--bg-card); border: 1px solid var(--border); color: var(--text);
            border-radius: var(--radius-sm); padding: 8px 12px; font-family: inherit;
            font-size: 13px; outline: none; transition: border-color 0.15s; color-scheme: dark; }
        .filter-bar select:focus, .filter-bar input:focus { border-color: var(--border-focus); }
        .account-row { display: flex; align-items: center; justify-content: space-between;
            padding: 14px 16px; border-bottom: 1px solid var(--border); }
        .account-row:last-child { border-bottom: none; }
        .account-name { font-weight: 500; }
        .account-type { font-size: 12px; color: var(--text-dim); margin-top: 2px; }
        .account-balance { font-size: 15px; font-weight: 700; }
        .default-badge { font-size: 10px; background: rgba(255,255,255,0.07);
            color: var(--text-muted); padding: 2px 7px; border-radius: 4px; margin-left: 6px; }
        h2 { font-size: 20px; font-weight: 700; letter-spacing: -0.02em; margin-bottom: 4px; }
        .page-desc { color: var(--text-muted); font-size: 13px; margin-bottom: 20px; }
        .pagination { display: flex; gap: 6px; justify-content: center; padding: 20px 0; }
        .page-btn { padding: 7px 13px; border-radius: var(--radius-sm); font-size: 13px;
            color: var(--text-muted); text-decoration: none; border: 1px solid var(--border);
            transition: all 0.15s; }
        .page-btn:hover, .page-btn.active { background: var(--bg-card); color: var(--text);
            border-color: var(--border-focus); }
        @keyframes fadeUp { from { opacity: 0; transform: translateY(8px); } to { opacity: 1; transform: translateY(0); } }
        .animate-in { animation: fadeUp 0.3s cubic-bezier(0.16, 1, 0.3, 1) both; }
        [x-cloak] { display: none !important; }
    </style>
</head>
<body>
<nav>
    <div class="nav-inner">
        <div class="nav-brand">Butler</div>
        <div class="nav-links">
            <a href="{{ route('dashboard.index') }}"   class="nav-link {{ request()->routeIs('dashboard.index')   ? 'active' : '' }}">Beranda</a>
            <a href="{{ route('dashboard.history') }}" class="nav-link {{ request()->routeIs('dashboard.history') ? 'active' : '' }}">Riwayat</a>
        </div>
    </div>
</nav>
<main class="layout" style="padding-top:28px;padding-bottom:40px">
    @yield('content')
</main>
</body>
</html>
