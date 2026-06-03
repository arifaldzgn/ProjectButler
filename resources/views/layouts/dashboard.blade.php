<!DOCTYPE html>
<html lang="id" x-data="butlerTheme()" x-init="init()" :data-theme="theme" x-cloak>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="theme-color" content="#0a0a0a" media="(prefers-color-scheme: dark)">
    <meta name="theme-color" content="#f4f4f5" media="(prefers-color-scheme: light)">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Butler Dashboard')</title>

    {{-- Anti-FOUC: apply theme before paint --}}
    <script>
        (function () {
            try {
                var saved = localStorage.getItem('butler-theme');
                var theme = saved || (window.matchMedia('(prefers-color-scheme: light)').matches ? 'light' : 'dark');
                document.documentElement.setAttribute('data-theme', theme);
            } catch (e) { document.documentElement.setAttribute('data-theme', 'dark'); }
        })();
    </script>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <script defer src="https://unpkg.com/alpinejs@3.x.x/dist/cdn.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>

    <style>
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

        /* ── Theme Tokens ───────────────────────────────────────── */
        :root[data-theme="dark"] {
            --bg:            #0a0a0a;
            --bg-soft:       #0f0f10;
            --bg-card:       #141417;
            --bg-elevated:   #1c1c1f;
            --bg-hover:      rgba(255,255,255,0.03);
            --border:        rgba(255,255,255,0.06);
            --border-strong: rgba(255,255,255,0.12);
            --border-focus:  rgba(255,255,255,0.20);
            --text:          #fafafa;
            --text-primary:  #fafafa;
            --text-secondary:#a1a1aa;
            --text-muted:    #71717a;
            --text-dim:      #52525b;
            --card-shadow:   0 0 0 1px rgba(255,255,255,0.02);
            --nav-bg:        rgba(10,10,10,0.78);
            --kpi-tile-bg:   rgba(255,255,255,0.04);
            --scrollbar:     #27272a;
            --color-scheme:  dark;
        }
        :root[data-theme="light"] {
            --bg:            #f4f4f5;
            --bg-soft:       #fafafa;
            --bg-card:       #ffffff;
            --bg-elevated:   #ffffff;
            --bg-hover:      rgba(0,0,0,0.025);
            --border:        rgba(0,0,0,0.06);
            --border-strong: rgba(0,0,0,0.12);
            --border-focus:  rgba(0,0,0,0.22);
            --text:          #0a0a0a;
            --text-primary:  #18181b;
            --text-secondary:#52525b;
            --text-muted:    #71717a;
            --text-dim:      #a1a1aa;
            --card-shadow:   0 1px 2px rgba(16,24,40,.04), 0 1px 3px rgba(16,24,40,.04);
            --nav-bg:        rgba(255,255,255,0.78);
            --kpi-tile-bg:   #ffffff;
            --scrollbar:     #d4d4d8;
            --color-scheme:  light;
        }

        /* Semantic colours (constant across themes) */
        :root {
            --red:     #ef4444;
            --green:   #10b981;
            --orange:  #f97316;
            --blue:    #3b82f6;
            --purple:  #8b5cf6;
            --yellow:  #eab308;
            --accent:  #8b5cf6;
            --success: #10b981;
            --warning: #f97316;
            --err:     #ef4444;
            --radius:    16px;
            --radius-sm: 10px;
            --radius-lg: 20px;
            --radius-pill: 999px;

            /* Gradient palette for icon tiles */
            --grad-purple: linear-gradient(135deg, #a855f7 0%, #7c3aed 100%);
            --grad-orange: linear-gradient(135deg, #fb923c 0%, #ea580c 100%);
            --grad-pink:   linear-gradient(135deg, #f472b6 0%, #db2777 100%);
            --grad-blue:   linear-gradient(135deg, #38bdf8 0%, #2563eb 100%);
            --grad-green:  linear-gradient(135deg, #34d399 0%, #059669 100%);
            --grad-yellow: linear-gradient(135deg, #fbbf24 0%, #d97706 100%);
            --grad-red:    linear-gradient(135deg, #fb7185 0%, #e11d48 100%);
        }

        html { color-scheme: var(--color-scheme); }
        html, body {
            background: var(--bg);
            color: var(--text);
            font-family: 'Inter', system-ui, -apple-system, sans-serif;
            font-size: 14px;
            line-height: 1.55;
            min-height: 100dvh;
            -webkit-font-smoothing: antialiased;
            -moz-osx-font-smoothing: grayscale;
            transition: background-color .3s ease, color .3s ease;
        }

        /* Smooth theme transition for common surfaces */
        .card, .table-wrap, nav, .field-input, .toggle-row, .fund-card,
        .nav-link, .page-btn, .btn-save, .btn-danger, .badge {
            transition: background-color .25s ease, color .25s ease,
                        border-color .25s ease, box-shadow .25s ease;
        }

        ::-webkit-scrollbar { width: 8px; height: 8px; }
        ::-webkit-scrollbar-thumb { background: var(--scrollbar); border-radius: 4px; }
        ::-webkit-scrollbar-track { background: transparent; }

        a { color: inherit; }
        [x-cloak] { display: none !important; }

        /* ── Layout ─────────────────────────────────────────────── */
        .layout {
            max-width: 880px;
            margin: 0 auto;
            padding: 24px 18px 80px;
        }
        @media (min-width: 768px) {
            .layout { padding: 32px 28px 80px; }
        }

        /* ── Nav ─────────────────────────────────────────────────── */
        nav {
            position: sticky; top: 0; z-index: 50;
            background: var(--nav-bg);
            backdrop-filter: saturate(180%) blur(20px);
            -webkit-backdrop-filter: saturate(180%) blur(20px);
            border-bottom: 1px solid var(--border);
        }
        .nav-inner {
            display: flex; align-items: center;
            justify-content: space-between; gap: 12px;
            padding: 0 18px;
            max-width: 880px; margin: 0 auto;
            height: 60px;
        }
        @media (min-width: 768px) { .nav-inner { padding: 0 28px; } }

        .nav-brand {
            display: inline-flex; align-items: center; gap: 8px;
            font-size: 14px; font-weight: 700;
            letter-spacing: -.01em;
            color: var(--text-primary); flex-shrink: 0;
        }
        .nav-brand .logo-dot {
            width: 28px; height: 28px; border-radius: 8px;
            background: var(--grad-purple);
            display: inline-flex; align-items: center; justify-content: center;
            font-size: 14px;
        }

        .nav-links { display: flex; align-items: center; gap: 2px; overflow-x: auto; -webkit-overflow-scrolling: touch; flex: 1; min-width: 0; justify-content: flex-end; }
        .nav-links::-webkit-scrollbar { display: none; }
        .nav-link {
            padding: 7px 12px; border-radius: var(--radius-sm);
            font-size: 13px; font-weight: 500;
            color: var(--text-muted); text-decoration: none;
            white-space: nowrap;
        }
        .nav-link:hover { background: var(--bg-hover); color: var(--text-secondary); }
        .nav-link.active { background: var(--bg-elevated); color: var(--text-primary);
                           box-shadow: var(--card-shadow); }

        /* Theme toggle button */
        .theme-toggle {
            flex-shrink: 0; width: 36px; height: 36px;
            display: inline-flex; align-items: center; justify-content: center;
            background: transparent; border: 1px solid var(--border);
            border-radius: 10px; cursor: pointer; color: var(--text-secondary);
            transition: background-color .2s, border-color .2s, color .2s;
        }
        .theme-toggle:hover { background: var(--bg-hover); color: var(--text-primary); border-color: var(--border-strong); }
        .theme-toggle svg { width: 16px; height: 16px; }

        /* ── Admin Banner ────────────────────────────────────────── */
        .admin-banner {
            background: linear-gradient(90deg, #fbbf24, #f59e0b); color: #111;
            padding: 10px 20px;
            display: flex; justify-content: space-between; align-items: center;
            font-size: 13px; font-weight: 600;
        }
        .admin-banner form { margin: 0; }
        .admin-banner button {
            background: rgba(0,0,0,0.12); border: none;
            padding: 6px 12px; border-radius: 8px;
            cursor: pointer; font-weight: 600; color: #000;
        }

        /* ── Page Header ─────────────────────────────────────────── */
        .page-header { margin-bottom: 22px; }
        .page-header h2 { font-size: 24px; font-weight: 700; letter-spacing: -.025em; color: var(--text-primary); }
        .page-header p  { color: var(--text-muted); font-size: 14px; margin-top: 4px; }
        @media (min-width: 768px) {
            .page-header h2 { font-size: 28px; }
        }

        /* ── Cards & Grids ───────────────────────────────────────── */
        .card {
            background: var(--bg-card);
            border: 1px solid var(--border);
            border-radius: var(--radius);
            padding: 20px;
            margin-bottom: 14px;
            box-shadow: var(--card-shadow);
        }
        @media (min-width: 768px) { .card { padding: 22px; } }

        .card-title {
            font-size: 13px; font-weight: 600;
            color: var(--text-secondary); margin-bottom: 14px;
            letter-spacing: -.01em;
        }
        .card-value {
            font-size: 26px; font-weight: 700;
            letter-spacing: -.025em; line-height: 1.1;
            color: var(--text-primary);
        }
        .card-subtitle { font-size: 12px; color: var(--text-muted); margin-top: 6px; }

        /* Premium KPI stat card — gradient icon tile + bold number */
        .stat-card { position: relative; overflow: hidden; }
        .stat-card .stat-icon {
            display: inline-flex; align-items: center; justify-content: center;
            width: 44px; height: 44px; border-radius: 12px;
            font-size: 20px; line-height: 1;
            margin-bottom: 14px;
            background: var(--grad-purple); color: #fff;
            box-shadow: 0 6px 14px -4px rgba(124,58,237,.45);
        }
        .stat-card.red    .stat-icon { background: var(--grad-pink);   box-shadow: 0 6px 14px -4px rgba(225,29,72,.4); }
        .stat-card.orange .stat-icon { background: var(--grad-orange); box-shadow: 0 6px 14px -4px rgba(234,88,12,.4); }
        .stat-card.green  .stat-icon { background: var(--grad-green);  box-shadow: 0 6px 14px -4px rgba(5,150,105,.4); }
        .stat-card.blue   .stat-icon { background: var(--grad-blue);   box-shadow: 0 6px 14px -4px rgba(37,99,235,.4); }
        .stat-card.yellow .stat-icon { background: var(--grad-yellow); box-shadow: 0 6px 14px -4px rgba(217,119,6,.4); }
        .stat-card.accent .stat-icon { background: var(--grad-purple); box-shadow: 0 6px 14px -4px rgba(124,58,237,.4); }

        .grid-2 { display: grid; grid-template-columns: 1fr;     gap: 12px; margin-bottom: 14px; }
        .grid-3 { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; margin-bottom: 14px; }
        .grid-4 { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; margin-bottom: 14px; }

        @media (min-width: 640px) {
            .grid-2 { grid-template-columns: 1fr 1fr; }
            .grid-3 { grid-template-columns: repeat(3, 1fr); }
        }
        @media (min-width: 900px) {
            .grid-4 { grid-template-columns: repeat(4, 1fr); }
        }

        /* ── Charts ─────────────────────────────────────────────── */
        .chart-container { position: relative; height: 220px; width: 100%; }
        .chart-container canvas { width: 100% !important; }

        /* ── Progress bars ───────────────────────────────────────── */
        .progress-bar, .progress-track {
            height: 6px; background: var(--bg-hover);
            border-radius: 999px; overflow: hidden; margin-top: 8px;
        }
        :root[data-theme="light"] .progress-bar,
        :root[data-theme="light"] .progress-track { background: rgba(0,0,0,.06); }

        .progress-fill { height: 100%; border-radius: 999px; transition: width .5s cubic-bezier(.16,1,.3,1); background: var(--text-primary); }
        .progress-fill.green  { background: var(--green); }
        .progress-fill.red    { background: var(--red); }
        .progress-fill.orange { background: var(--orange); }
        .progress-fill.blue   { background: var(--blue); }
        .progress-fill.accent { background: var(--accent); }

        /* ── Tables ─────────────────────────────────────────────── */
        .table-wrap {
            background: var(--bg-card); border: 1px solid var(--border);
            border-radius: var(--radius); overflow: hidden; margin-bottom: 14px;
            box-shadow: var(--card-shadow);
        }
        .data-table, table { width: 100%; border-collapse: collapse; font-size: 13px; }
        .data-table thead tr, thead tr { border-bottom: 1px solid var(--border); }
        .data-table th, th {
            padding: 14px 16px; text-align: left;
            font-size: 11px; font-weight: 600;
            letter-spacing: .05em; text-transform: uppercase; color: var(--text-muted);
            background: transparent;
        }
        .data-table td, td {
            padding: 14px 16px;
            border-bottom: 1px solid var(--border);
            vertical-align: middle; color: var(--text-secondary);
        }
        .data-table tr:last-child td, tr:last-child td { border-bottom: none; }
        .data-table tr:hover td, tbody tr:hover td { background: var(--bg-hover); }

        /* Mobile: table becomes scrollable */
        .table-wrap { overflow-x: auto; }
        .table-wrap table { min-width: 480px; }

        /* ── Badges ─────────────────────────────────────────────── */
        .badge {
            display: inline-flex; align-items: center; gap: 4px;
            padding: 3px 10px;
            border-radius: var(--radius-pill);
            font-size: 11px; font-weight: 600;
            letter-spacing: .01em; text-transform: capitalize;
            line-height: 1.4;
        }
        .badge::before {
            content: ''; width: 6px; height: 6px; border-radius: 999px;
            background: currentColor; opacity: .9;
        }
        .badge-expense  { background: rgba(239,68,68,.12);  color: #ef4444; }
        .badge-meal     { background: rgba(249,115,22,.12); color: #ea580c; }
        .badge-food     { background: rgba(249,115,22,.12); color: #ea580c; }
        .badge-income   { background: rgba(16,185,129,.12); color: #059669; }
        .badge-saving   { background: rgba(99,102,241,.12); color: #6366f1; }
        .badge-parse    { background: rgba(59,130,246,.12); color: #2563eb; }
        .badge-summary  { background: rgba(139,92,246,.12); color: #7c3aed; }
        .badge-transfer { background: rgba(234,179,8,.14);  color: #b45309; }
        .badge-bill_payment  { background: rgba(239,68,68,.12); color: #dc2626; }
        .badge-debt_payment  { background: rgba(239,68,68,.12); color: #dc2626; }
        .badge-sinking_fund_deposit { background: rgba(99,102,241,.12); color: #6366f1; }
        :root[data-theme="dark"] .badge-expense   { color: #fca5a5; }
        :root[data-theme="dark"] .badge-meal,
        :root[data-theme="dark"] .badge-food      { color: #fdba74; }
        :root[data-theme="dark"] .badge-income    { color: #6ee7b7; }
        :root[data-theme="dark"] .badge-saving    { color: #a5b4fc; }
        :root[data-theme="dark"] .badge-parse     { color: #93c5fd; }
        :root[data-theme="dark"] .badge-summary   { color: #c4b5fd; }
        :root[data-theme="dark"] .badge-transfer  { color: #fde68a; }
        :root[data-theme="dark"] .badge-bill_payment,
        :root[data-theme="dark"] .badge-debt_payment { color: #fca5a5; }
        :root[data-theme="dark"] .badge-sinking_fund_deposit { color: #a5b4fc; }

        /* Status pills (success/pending/failed) */
        .pill {
            display: inline-flex; align-items: center; gap: 6px;
            padding: 4px 10px; border-radius: var(--radius-pill);
            font-size: 12px; font-weight: 600;
        }
        .pill-success { background: rgba(16,185,129,.12); color: #059669; }
        .pill-warning { background: rgba(245,158,11,.12); color: #d97706; }
        .pill-danger  { background: rgba(239,68,68,.12);  color: #dc2626; }
        :root[data-theme="dark"] .pill-success { color: #6ee7b7; }
        :root[data-theme="dark"] .pill-warning { color: #fde68a; }
        :root[data-theme="dark"] .pill-danger  { color: #fca5a5; }

        /* ── Amount colours ──────────────────────────────────────── */
        .amount-expense { color: #dc2626; font-weight: 600; }
        .amount-income  { color: #059669; font-weight: 600; }
        :root[data-theme="dark"] .amount-expense { color: #fca5a5; }
        :root[data-theme="dark"] .amount-income  { color: #6ee7b7; }

        /* ── Filter bar ─────────────────────────────────────────── */
        .filter-bar { display: flex; gap: 10px; flex-wrap: wrap; margin: 16px 0; }
        .filter-bar select,
        .filter-bar input[type="date"] {
            background: var(--bg-card); border: 1px solid var(--border);
            color: var(--text-primary); border-radius: var(--radius-sm);
            padding: 9px 14px; font-family: inherit; font-size: 13px;
            outline: none; transition: border-color .15s; color-scheme: var(--color-scheme);
        }
        .filter-bar select:focus,
        .filter-bar input:focus { border-color: var(--border-focus); }

        /* ── Section title ───────────────────────────────────────── */
        .section-title {
            font-size: 13px; font-weight: 600;
            color: var(--text-secondary);
            margin: 28px 0 12px;
            display: flex; align-items: center; justify-content: space-between;
        }

        /* ── Account row ─────────────────────────────────────────── */
        .account-row {
            display: flex; align-items: center; justify-content: space-between;
            padding: 16px 18px; border-bottom: 1px solid var(--border);
        }
        .account-row:last-child { border-bottom: none; }
        .account-name    { font-weight: 600; color: var(--text-primary); font-size: 14px; }
        .account-type    { font-size: 12px; color: var(--text-muted); margin-top: 3px; }
        .account-balance { font-size: 15px; font-weight: 700; color: var(--text-primary); }
        .default-badge {
            font-size: 10px; background: var(--bg-hover);
            color: var(--text-muted); padding: 2px 8px;
            border-radius: var(--radius-pill); margin-left: 8px;
            font-weight: 600; letter-spacing: .04em; text-transform: uppercase;
        }

        /* ── Fund card ───────────────────────────────────────────── */
        .fund-card {
            display: flex; flex-direction: column; padding: 18px;
            background: var(--bg-card); border: 1px solid var(--border);
            border-radius: var(--radius); margin-bottom: 12px;
            box-shadow: var(--card-shadow);
        }
        .fund-header  { display: flex; justify-content: space-between; align-items: flex-end; margin-bottom: 8px; gap: 12px; }
        .fund-name    { font-weight: 600; font-size: 14px; color: var(--text-primary); }
        .fund-balance { font-size: 16px; font-weight: 700; color: var(--text-primary); white-space: nowrap; }
        .fund-target  { font-size: 12px; color: var(--text-muted); margin-bottom: 10px; }

        /* ── Meal item ───────────────────────────────────────────── */
        .meal-item {
            display: flex; justify-content: space-between; align-items: center;
            padding: 14px 0; border-bottom: 1px solid var(--border);
        }
        .meal-item:last-child { border-bottom: none; }
        .meal-info h4 { font-size: 14px; font-weight: 600; margin-bottom: 2px; color: var(--text-primary); }
        .meal-info p  { font-size: 12px; color: var(--text-muted); }
        .meal-cal     { font-weight: 700; font-size: 15px; color: var(--orange); white-space: nowrap; margin-left: 12px; }

        /* ── Empty state ─────────────────────────────────────────── */
        .empty-state {
            text-align: center; padding: 44px 20px;
            color: var(--text-muted);
        }
        .empty-icon { font-size: 36px; margin-bottom: 10px; }
        .empty-state h3 { font-size: 15px; font-weight: 600; margin-bottom: 4px; color: var(--text-secondary); }
        .empty-state p  { font-size: 13px; }

        /* ── Form inputs (settings) ──────────────────────────────── */
        .field { margin-bottom: 18px; }
        .field-label {
            display: block; font-size: 13px; font-weight: 500;
            color: var(--text-secondary); margin-bottom: 7px;
        }
        .field-input {
            width: 100%; padding: 11px 14px;
            background: var(--bg-elevated); border: 1px solid var(--border);
            border-radius: var(--radius-sm); color: var(--text-primary);
            font-family: inherit; font-size: 14px;
            outline: none; transition: border-color .15s, box-shadow .15s;
            -webkit-appearance: none; appearance: none; color-scheme: var(--color-scheme);
        }
        .field-input:focus { border-color: var(--border-focus); box-shadow: 0 0 0 3px var(--bg-hover); }
        select.field-input {
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='16' height='16' fill='currentColor' viewBox='0 0 16 16'%3E%3Cpath d='M7.247 11.14 2.451 5.658C1.885 5.013 2.345 4 3.204 4h9.592a1 1 0 0 1 .753 1.659l-4.796 5.48a1 1 0 0 1-1.506 0z'/%3E%3C/svg%3E");
            background-repeat: no-repeat; background-position: right 12px center; padding-right: 36px;
        }
        .btn-save {
            display: inline-flex; align-items: center; gap: 8px;
            padding: 11px 22px; background: var(--text-primary); color: var(--bg-card);
            border: none; border-radius: var(--radius-sm);
            font-family: inherit; font-size: 14px; font-weight: 600;
            cursor: pointer; transition: opacity .15s, transform .1s;
        }
        .btn-save:hover  { opacity: .88; }
        .btn-save:active { transform: scale(.98); }
        .btn-danger {
            background: rgba(239,68,68,.1); color: #dc2626;
            border: 1px solid rgba(239,68,68,.22);
            padding: 11px 22px; border-radius: var(--radius-sm);
            font-family: inherit; font-size: 14px; font-weight: 600;
            cursor: pointer;
        }
        .btn-danger:hover { background: rgba(239,68,68,.18); }
        :root[data-theme="dark"] .btn-danger { color: #fca5a5; }

        /* ── Toggle ─────────────────────────────────────────────── */
        .toggle-row {
            display: flex; align-items: center; gap: 14px;
            padding: 16px; background: var(--bg-card);
            border: 1px solid var(--border); border-radius: var(--radius);
            cursor: pointer; margin-bottom: 10px;
        }
        .toggle-row:hover { border-color: var(--border-strong); }
        .toggle-visual {
            width: 42px; height: 24px; border-radius: 999px;
            background: var(--bg-hover); border: 1px solid var(--border);
            position: relative; transition: background .25s, border-color .25s; flex-shrink: 0;
        }
        .toggle-visual::after {
            content: ''; position: absolute;
            width: 18px; height: 18px; border-radius: 50%;
            background: var(--text-dim); top: 2px; left: 2px;
            transition: transform .25s, background .25s;
        }
        .toggle-row.is-on .toggle-visual { background: var(--grad-purple); border-color: transparent; }
        .toggle-row.is-on .toggle-visual::after { transform: translateX(18px); background: #fff; }
        .toggle-label { font-size: 14px; font-weight: 600; color: var(--text-primary); }
        .toggle-desc  { font-size: 12px; color: var(--text-muted); margin-top: 2px; }

        /* ── Misc helpers ───────────────────────────────────────── */
        h2 { font-size: 22px; font-weight: 700; letter-spacing: -.025em; color: var(--text-primary); }
        .page-desc { color: var(--text-muted); font-size: 13px; margin-bottom: 16px; }
        .animate-in { animation: fadeUp .35s cubic-bezier(.16,1,.3,1) both; }
        @keyframes fadeUp { from { opacity:0; transform: translateY(10px); } to { opacity:1; transform: translateY(0); } }

        .alert-success {
            padding: 13px 16px; background: rgba(16,185,129,.1);
            border: 1px solid rgba(16,185,129,.22); border-radius: var(--radius-sm);
            color: #059669; font-size: 13px; font-weight: 500; margin-bottom: 16px;
        }
        .alert-error {
            padding: 13px 16px; background: rgba(239,68,68,.1);
            border: 1px solid rgba(239,68,68,.22); border-radius: var(--radius-sm);
            color: #dc2626; font-size: 13px; font-weight: 500; margin-bottom: 16px;
        }
        :root[data-theme="dark"] .alert-success { color: #6ee7b7; }
        :root[data-theme="dark"] .alert-error { color: #fca5a5; }

        /* Chart helpers */
        .chart-legend { display: flex; gap: 16px; flex-wrap: wrap; margin-top: 10px; }
        .chart-legend-item { display: flex; align-items: center; gap: 6px; font-size: 12px; color: var(--text-muted); }
        .chart-legend-dot { width: 8px; height: 8px; border-radius: 50%; }

        /* Pagination */
        .pagination { display: flex; gap: 6px; justify-content: center; padding: 20px 0; flex-wrap: wrap; }
        .page-btn {
            padding: 8px 13px; border-radius: var(--radius-sm); font-size: 13px;
            color: var(--text-muted); text-decoration: none;
            border: 1px solid var(--border);
        }
        .page-btn:hover, .page-btn.active {
            background: var(--bg-card); color: var(--text-primary); border-color: var(--border-strong);
        }

        /* Stat sub helpers for legacy stat-card markup */
        .stat-card .stat-label {
            font-size: 11px; color: var(--text-muted);
            letter-spacing: .06em; text-transform: uppercase; margin-bottom: 8px;
        }
        .stat-card .stat-value { font-size: 22px; font-weight: 700; letter-spacing: -.02em; color: var(--text-primary); }
        .stat-card .stat-sub   { font-size: 12px; color: var(--text-muted); margin-top: 4px; }
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
        <a href="{{ route('dashboard.index') }}" class="nav-brand" style="text-decoration:none">
            <span class="logo-dot">🤖</span>
            <span>Butler</span>
        </a>
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
            <a href="{{ route('dashboard.distribution') }}"
               class="nav-link {{ request()->routeIs('dashboard.distribution') ? 'active' : '' }}">Distribusi</a>
            <a href="{{ route('dashboard.cashflow') }}"
               class="nav-link {{ request()->routeIs('dashboard.cashflow') ? 'active' : '' }}">Cashflow</a>
            <a href="{{ route('dashboard.timeline') }}"
               class="nav-link {{ request()->routeIs('dashboard.timeline') ? 'active' : '' }}">Timeline</a>
            <a href="{{ route('dashboard.debts') }}"
               class="nav-link {{ request()->routeIs('dashboard.debts') ? 'active' : '' }}">Hutang</a>
            <a href="{{ route('dashboard.history') }}"
               class="nav-link {{ request()->routeIs('dashboard.history') ? 'active' : '' }}">Riwayat</a>
            <a href="{{ route('dashboard.memory') }}"
               class="nav-link {{ request()->routeIs('dashboard.memory') ? 'active' : '' }}">Memory</a>
            <a href="{{ route('dashboard.settings') }}"
               class="nav-link {{ request()->routeIs('dashboard.settings') ? 'active' : '' }}">Settings</a>
            @if(request()->dashboard_user?->isAdmin() && !session()->has('admin_impersonator_id'))
            <a href="{{ route('admin.users.index') }}"
               class="nav-link {{ request()->routeIs('admin.*') ? 'active' : '' }}"
               style="color: #d97706">Admin</a>
            @endif
        </div>
        <button type="button" class="theme-toggle" @click="toggle()" :aria-label="theme === 'dark' ? 'Switch to light mode' : 'Switch to dark mode'">
            <template x-if="theme === 'dark'">
                {{-- sun --}}
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <circle cx="12" cy="12" r="4"/>
                    <path d="M12 2v2M12 20v2M4.93 4.93l1.41 1.41M17.66 17.66l1.41 1.41M2 12h2M20 12h2M4.93 19.07l1.41-1.41M17.66 6.34l1.41-1.41"/>
                </svg>
            </template>
            <template x-if="theme === 'light'">
                {{-- moon --}}
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z"/>
                </svg>
            </template>
        </button>
    </div>
</nav>

<main class="layout">
    @if(session('success'))
        <div class="alert-success">✓ {{ session('success') }}</div>
    @endif
    @if(session('error'))
        <div class="alert-error">{{ session('error') }}</div>
    @endif
    @yield('content')
</main>

<script>
// Theme controller (Alpine)
function butlerTheme() {
    return {
        theme: 'dark',
        init() {
            const saved = localStorage.getItem('butler-theme');
            const sys   = window.matchMedia('(prefers-color-scheme: light)').matches ? 'light' : 'dark';
            this.theme  = saved || sys;
            this.$watch('theme', v => {
                document.documentElement.setAttribute('data-theme', v);
                localStorage.setItem('butler-theme', v);
                this.refreshCharts();
            });
        },
        toggle() { this.theme = this.theme === 'dark' ? 'light' : 'dark'; },
        refreshCharts() {
            // Re-apply Chart.js global colours and update existing instances
            if (typeof Chart === 'undefined') return;
            const css = getComputedStyle(document.documentElement);
            Chart.defaults.color       = css.getPropertyValue('--text-muted').trim();
            Chart.defaults.borderColor = css.getPropertyValue('--border').trim();
            Object.values(Chart.instances || {}).forEach(ch => ch.update('none'));
        }
    };
}

// Initial Chart.js defaults pulled from current CSS vars
(function () {
    const css = getComputedStyle(document.documentElement);
    Chart.defaults.color       = css.getPropertyValue('--text-muted').trim() || '#71717a';
    Chart.defaults.borderColor = css.getPropertyValue('--border').trim()     || 'rgba(0,0,0,0.06)';
    Chart.defaults.font.family = "'Inter', system-ui, sans-serif";
    Chart.defaults.font.size = 12;
})();

function formatRupiah(v) {
    if (v >= 1000000) return 'Rp ' + (v/1000000).toFixed(1) + 'jt';
    if (v >= 1000)    return 'Rp ' + (v/1000).toFixed(0) + 'k';
    return 'Rp ' + Math.round(v);
}
</script>

@yield('scripts')
</body>
</html>
