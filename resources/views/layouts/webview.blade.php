<!DOCTYPE html>
<html lang="id" class="scroll-smooth">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="theme-color" content="#0a0a0a">
    <title>@yield('title', 'Butler Setup')</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <script defer src="https://unpkg.com/alpinejs@3.x.x/dist/cdn.min.js"></script>
    <style>
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

        :root {
            --bg:          #0a0a0a;
            --bg-card:     #141414;
            --bg-elevated: #1c1c1c;
            --border:      rgba(255,255,255,0.08);
            --border-focus:rgba(255,255,255,0.22);
            --text:        #f5f5f5;
            --text-muted:  #888;
            --text-dim:    #555;
            --accent:      #ffffff;
            --accent-dim:  rgba(255,255,255,0.06);
            --success:     #22c55e;
            --warning:     #f59e0b;
            --error-color: #ef4444;
            --radius:      12px;
            --radius-sm:   8px;
            --shadow:      0 1px 3px rgba(0,0,0,0.6), 0 8px 24px rgba(0,0,0,0.3);
        }

        html, body {
            background: var(--bg);
            color: var(--text);
            font-family: 'Inter', system-ui, sans-serif;
            font-size: 15px;
            line-height: 1.6;
            min-height: 100dvh;
            -webkit-font-smoothing: antialiased;
        }

        .page {
            max-width: 480px;
            margin: 0 auto;
            min-height: 100dvh;
            padding: env(safe-area-inset-top, 0px) 20px env(safe-area-inset-bottom, 0px);
            display: flex;
            flex-direction: column;
        }

        /* ── Progress Bar ── */
        .progress-wrap {
            padding: 24px 0 0;
        }
        .progress-label {
            font-size: 12px;
            color: var(--text-dim);
            letter-spacing: 0.08em;
            text-transform: uppercase;
            margin-bottom: 10px;
        }
        .progress-bar-track {
            height: 2px;
            background: var(--border);
            border-radius: 2px;
            overflow: hidden;
        }
        .progress-bar-fill {
            height: 100%;
            background: var(--accent);
            border-radius: 2px;
            transition: width 0.6s cubic-bezier(0.16, 1, 0.3, 1);
        }

        /* ── Header ── */
        .page-header {
            padding: 36px 0 8px;
        }
        .butler-mark {
            font-size: 11px;
            font-weight: 600;
            letter-spacing: 0.12em;
            text-transform: uppercase;
            color: var(--text-dim);
            margin-bottom: 20px;
        }
        h1 {
            font-size: 26px;
            font-weight: 700;
            line-height: 1.25;
            color: var(--text);
            letter-spacing: -0.02em;
        }
        .subtitle {
            font-size: 14px;
            color: var(--text-muted);
            margin-top: 8px;
        }

        /* ── Form Elements ── */
        .form-body {
            flex: 1;
            padding: 28px 0;
        }
        .field {
            margin-bottom: 20px;
        }
        label.field-label {
            display: block;
            font-size: 13px;
            font-weight: 500;
            color: var(--text-muted);
            margin-bottom: 8px;
            letter-spacing: 0.02em;
        }
        input[type="text"],
        input[type="number"],
        input[type="time"],
        select {
            width: 100%;
            padding: 13px 16px;
            background: var(--bg-card);
            border: 1px solid var(--border);
            border-radius: var(--radius-sm);
            color: var(--text);
            font-family: inherit;
            font-size: 15px;
            outline: none;
            transition: border-color 0.15s, box-shadow 0.15s;
            -webkit-appearance: none;
            appearance: none;
        }
        input:focus, select:focus {
            border-color: var(--border-focus);
            box-shadow: 0 0 0 3px rgba(255,255,255,0.05);
        }
        select {
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='16' height='16' fill='%23666' viewBox='0 0 16 16'%3E%3Cpath d='M7.247 11.14 2.451 5.658C1.885 5.013 2.345 4 3.204 4h9.592a1 1 0 0 1 .753 1.659l-4.796 5.48a1 1 0 0 1-1.506 0z'/%3E%3C/svg%3E");
            background-repeat: no-repeat;
            background-position: right 14px center;
            padding-right: 40px;
        }
        .field-error {
            font-size: 12px;
            color: var(--error-color);
            margin-top: 6px;
        }

        /* ── Chip Selector ── */
        .chips {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
        }
        .chip {
            padding: 8px 16px;
            border: 1px solid var(--border);
            border-radius: 100px;
            font-size: 13px;
            font-weight: 500;
            color: var(--text-muted);
            cursor: pointer;
            transition: all 0.15s;
            user-select: none;
            background: transparent;
        }
        .chip:hover  { border-color: var(--border-focus); color: var(--text); }
        .chip.active { background: var(--text); color: var(--bg); border-color: var(--text); }

        /* ── Cards / Account Items ── */
        .card {
            background: var(--bg-card);
            border: 1px solid var(--border);
            border-radius: var(--radius);
            padding: 16px;
            margin-bottom: 10px;
            transition: border-color 0.15s;
        }
        .card:hover { border-color: var(--border-focus); }
        .card-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 4px;
        }
        .card-title {
            font-weight: 600;
            font-size: 15px;
        }
        .card-subtitle {
            font-size: 12px;
            color: var(--text-dim);
        }
        .remove-btn {
            background: none;
            border: none;
            color: var(--text-dim);
            cursor: pointer;
            padding: 4px 8px;
            border-radius: 6px;
            font-size: 16px;
            line-height: 1;
            transition: color 0.15s, background 0.15s;
        }
        .remove-btn:hover { color: var(--error-color); background: rgba(239,68,68,0.1); }

        /* ── Toggle / Checkbox ── */
        .toggle-row {
            display: flex;
            align-items: center;
            gap: 14px;
            padding: 16px;
            background: var(--bg-card);
            border: 1px solid var(--border);
            border-radius: var(--radius);
            cursor: pointer;
            transition: border-color 0.15s;
        }
        .toggle-row:hover { border-color: var(--border-focus); }
        .toggle-row input[type="checkbox"] { display: none; }
        .toggle-visual {
            width: 40px;
            height: 24px;
            border-radius: 12px;
            background: var(--bg-elevated);
            border: 1px solid var(--border);
            position: relative;
            transition: background 0.2s;
            flex-shrink: 0;
        }
        .toggle-visual::after {
            content: '';
            position: absolute;
            width: 18px;
            height: 18px;
            border-radius: 50%;
            background: var(--text-dim);
            top: 2px;
            left: 2px;
            transition: transform 0.2s, background 0.2s;
        }
        .toggle-row.is-on .toggle-visual { background: rgba(255,255,255,0.1); border-color: var(--border-focus); }
        .toggle-row.is-on .toggle-visual::after { transform: translateX(16px); background: var(--text); }
        .toggle-label { font-size: 15px; font-weight: 500; }
        .toggle-desc  { font-size: 13px; color: var(--text-muted); margin-top: 2px; }

        /* ── Radio (default account selector) ── */
        .radio-row {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 10px 0 0;
            cursor: pointer;
            font-size: 13px;
            color: var(--text-muted);
        }
        .radio-visual {
            width: 18px; height: 18px;
            border-radius: 50%;
            border: 1px solid var(--border);
            display: flex; align-items: center; justify-content: center;
            flex-shrink: 0;
            transition: border-color 0.15s;
        }
        .radio-visual.is-checked { border-color: var(--accent); }
        .radio-visual.is-checked::after {
            content: '';
            width: 10px; height: 10px;
            border-radius: 50%;
            background: var(--accent);
        }

        /* ── Buttons ── */
        .btn {
            display: block;
            width: 100%;
            padding: 15px;
            border: none;
            border-radius: var(--radius-sm);
            font-family: inherit;
            font-size: 15px;
            font-weight: 600;
            cursor: pointer;
            text-align: center;
            transition: opacity 0.15s, transform 0.1s;
            text-decoration: none;
        }
        .btn:active { transform: scale(0.99); }
        .btn-primary { background: var(--text); color: var(--bg); }
        .btn-primary:hover { opacity: 0.9; }
        .btn-ghost {
            background: transparent;
            color: var(--text-dim);
            border: 1px solid var(--border);
            margin-top: 10px;
        }
        .btn-ghost:hover { border-color: var(--border-focus); color: var(--text-muted); }
        .btn:disabled { opacity: 0.35; cursor: not-allowed; }

        /* ── Sticky footer ── */
        .form-footer {
            padding: 16px 0 max(24px, env(safe-area-inset-bottom, 24px));
            position: sticky;
            bottom: 0;
            background: linear-gradient(to bottom, transparent, var(--bg) 40%);
            margin-top: auto;
        }

        /* ── Animations ── */
        @keyframes fadeUp {
            from { opacity: 0; transform: translateY(12px); }
            to   { opacity: 1; transform: translateY(0); }
        }
        .animate-in { animation: fadeUp 0.35s cubic-bezier(0.16, 1, 0.3, 1) both; }
        .animate-in-delay-1 { animation-delay: 0.05s; }
        .animate-in-delay-2 { animation-delay: 0.10s; }
        .animate-in-delay-3 { animation-delay: 0.15s; }

        /* ── Subform reveal ── */
        [x-cloak] { display: none !important; }
        .subform {
            overflow: hidden;
            border-top: 1px solid var(--border);
            margin-top: 12px;
            padding-top: 12px;
        }
    </style>
</head>
<body>
<div class="page">
    @yield('content')
</div>
</body>
</html>
