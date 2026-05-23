<!DOCTYPE html>
<html lang="en" class="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>@yield('title', 'Butler') — Project Butler</title>
    <meta name="description" content="Personal AI assistant for daily life tracking">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4"></script>
    <style>
        :root {
            --bg-primary: #0a0a0f;
            --bg-secondary: #111118;
            --bg-card: #16161f;
            --bg-card-hover: #1c1c28;
            --border: #23232f;
            --text-primary: #eeeef0;
            --text-secondary: #8888a0;
            --text-muted: #55556a;
            --accent: #6c5ce7;
            --accent-light: #a29bfe;
            --accent-glow: rgba(108, 92, 231, 0.15);
            --green: #00d2a0;
            --green-glow: rgba(0, 210, 160, 0.15);
            --red: #ff6b81;
            --red-glow: rgba(255, 107, 129, 0.15);
            --orange: #ffa726;
            --orange-glow: rgba(255, 167, 38, 0.15);
            --blue: #42a5f5;
            --blue-glow: rgba(66, 165, 245, 0.15);
            --yellow: #ffee58;
            --radius: 16px;
            --radius-sm: 10px;
            --shadow: 0 4px 24px rgba(0,0,0,0.3);
        }

        * { margin: 0; padding: 0; box-sizing: border-box; }

        body {
            font-family: 'Inter', -apple-system, sans-serif;
            background: var(--bg-primary);
            color: var(--text-primary);
            min-height: 100vh;
            -webkit-font-smoothing: antialiased;
        }

        /* Layout */
        .app-layout { display: flex; min-height: 100vh; }

        /* Sidebar */
        .sidebar {
            width: 260px;
            background: var(--bg-secondary);
            border-right: 1px solid var(--border);
            padding: 24px 16px;
            display: flex;
            flex-direction: column;
            position: fixed;
            top: 0;
            left: 0;
            height: 100vh;
            z-index: 50;
            transition: transform 0.3s ease;
        }

        .sidebar-brand {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 0 8px 24px;
            border-bottom: 1px solid var(--border);
            margin-bottom: 24px;
        }

        .sidebar-brand .brand-icon {
            width: 40px;
            height: 40px;
            background: linear-gradient(135deg, var(--accent), var(--accent-light));
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 20px;
        }

        .sidebar-brand h1 {
            font-size: 18px;
            font-weight: 700;
            letter-spacing: -0.5px;
        }

        .sidebar-brand small {
            display: block;
            font-size: 11px;
            color: var(--text-muted);
            font-weight: 400;
        }

        .nav-section {
            font-size: 10px;
            text-transform: uppercase;
            letter-spacing: 1.5px;
            color: var(--text-muted);
            padding: 0 12px;
            margin-bottom: 8px;
            margin-top: 16px;
        }

        .nav-link {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 10px 12px;
            border-radius: var(--radius-sm);
            color: var(--text-secondary);
            text-decoration: none;
            font-size: 14px;
            font-weight: 500;
            transition: all 0.2s;
            margin-bottom: 2px;
        }

        .nav-link:hover {
            background: var(--bg-card);
            color: var(--text-primary);
        }

        .nav-link.active {
            background: var(--accent-glow);
            color: var(--accent-light);
        }

        .nav-link .nav-icon { font-size: 18px; width: 24px; text-align: center; }

        /* Main Content */
        .main-content {
            flex: 1;
            margin-left: 260px;
            padding: 32px;
            max-width: 1200px;
        }

        .page-header {
            margin-bottom: 32px;
        }

        .page-header h2 {
            font-size: 28px;
            font-weight: 800;
            letter-spacing: -1px;
            margin-bottom: 4px;
        }

        .page-header p {
            color: var(--text-secondary);
            font-size: 14px;
        }

        /* Cards */
        .card {
            background: var(--bg-card);
            border: 1px solid var(--border);
            border-radius: var(--radius);
            padding: 24px;
            transition: all 0.2s;
        }

        .card:hover {
            border-color: rgba(108, 92, 231, 0.3);
            box-shadow: 0 0 30px var(--accent-glow);
        }

        .card-title {
            font-size: 12px;
            text-transform: uppercase;
            letter-spacing: 1px;
            color: var(--text-muted);
            margin-bottom: 8px;
            font-weight: 600;
        }

        .card-value {
            font-size: 28px;
            font-weight: 800;
            letter-spacing: -1px;
        }

        .card-subtitle {
            font-size: 13px;
            color: var(--text-secondary);
            margin-top: 4px;
        }

        /* Grid */
        .grid-4 {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 16px;
            margin-bottom: 24px;
        }

        .grid-2 {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 16px;
            margin-bottom: 24px;
        }

        .grid-3 {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 16px;
            margin-bottom: 24px;
        }

        /* Stat Cards */
        .stat-card {
            position: relative;
            overflow: hidden;
        }

        .stat-card::before {
            content: '';
            position: absolute;
            top: 0;
            right: 0;
            width: 80px;
            height: 80px;
            border-radius: 50%;
            filter: blur(40px);
            opacity: 0.4;
        }

        .stat-card.green::before { background: var(--green); }
        .stat-card.red::before { background: var(--red); }
        .stat-card.accent::before { background: var(--accent); }
        .stat-card.orange::before { background: var(--orange); }
        .stat-card.blue::before { background: var(--blue); }

        .stat-card .stat-icon {
            font-size: 24px;
            margin-bottom: 12px;
        }

        /* Progress Bar */
        .progress-bar {
            width: 100%;
            height: 6px;
            background: var(--bg-primary);
            border-radius: 3px;
            margin-top: 12px;
            overflow: hidden;
        }

        .progress-fill {
            height: 100%;
            border-radius: 3px;
            transition: width 0.8s ease;
        }

        .progress-fill.green { background: linear-gradient(90deg, var(--green), #00e6b0); }
        .progress-fill.red { background: linear-gradient(90deg, var(--red), #ff8fa3); }
        .progress-fill.accent { background: linear-gradient(90deg, var(--accent), var(--accent-light)); }
        .progress-fill.orange { background: linear-gradient(90deg, var(--orange), #ffcc80); }

        /* Table */
        .data-table {
            width: 100%;
            border-collapse: collapse;
        }

        .data-table th {
            text-align: left;
            font-size: 11px;
            text-transform: uppercase;
            letter-spacing: 1px;
            color: var(--text-muted);
            padding: 12px 16px;
            border-bottom: 1px solid var(--border);
            font-weight: 600;
        }

        .data-table td {
            padding: 12px 16px;
            font-size: 14px;
            border-bottom: 1px solid rgba(35, 35, 47, 0.5);
        }

        .data-table tr:hover td {
            background: var(--bg-card-hover);
        }

        /* Badges */
        .badge {
            display: inline-block;
            padding: 3px 10px;
            border-radius: 20px;
            font-size: 11px;
            font-weight: 600;
            text-transform: capitalize;
        }

        .badge-expense { background: var(--red-glow); color: var(--red); }
        .badge-income { background: var(--green-glow); color: var(--green); }
        .badge-savings { background: var(--blue-glow); color: var(--blue); }
        .badge-food { background: var(--orange-glow); color: var(--orange); }

        /* Chart container */
        .chart-container {
            position: relative;
            height: 280px;
            margin-top: 16px;
        }

        /* Meal list */
        .meal-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 12px 0;
            border-bottom: 1px solid rgba(35, 35, 47, 0.5);
        }

        .meal-item:last-child { border-bottom: none; }

        .meal-info h4 { font-size: 14px; font-weight: 600; }
        .meal-info p { font-size: 12px; color: var(--text-secondary); margin-top: 2px; }
        .meal-cal { font-size: 16px; font-weight: 700; color: var(--orange); }

        /* Empty state */
        .empty-state {
            text-align: center;
            padding: 48px 24px;
            color: var(--text-muted);
        }

        .empty-state .empty-icon { font-size: 48px; margin-bottom: 16px; }
        .empty-state h3 { font-size: 16px; color: var(--text-secondary); margin-bottom: 8px; }
        .empty-state p { font-size: 13px; }

        /* Mobile sidebar toggle */
        .mobile-toggle {
            display: none;
            position: fixed;
            top: 16px;
            left: 16px;
            z-index: 60;
            background: var(--bg-card);
            border: 1px solid var(--border);
            border-radius: var(--radius-sm);
            padding: 8px 12px;
            color: var(--text-primary);
            font-size: 20px;
            cursor: pointer;
        }

        .sidebar-overlay {
            display: none;
            position: fixed;
            inset: 0;
            background: rgba(0,0,0,0.5);
            z-index: 40;
        }

        /* Animations */
        @keyframes fadeInUp {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .animate-in {
            animation: fadeInUp 0.5s ease forwards;
            opacity: 0;
        }

        .animate-in:nth-child(1) { animation-delay: 0.05s; }
        .animate-in:nth-child(2) { animation-delay: 0.1s; }
        .animate-in:nth-child(3) { animation-delay: 0.15s; }
        .animate-in:nth-child(4) { animation-delay: 0.2s; }
        .animate-in:nth-child(5) { animation-delay: 0.25s; }
        .animate-in:nth-child(6) { animation-delay: 0.3s; }

        /* Responsive */
        @media (max-width: 1024px) {
            .grid-4 { grid-template-columns: repeat(2, 1fr); }
        }

        @media (max-width: 768px) {
            .sidebar { transform: translateX(-100%); }
            .sidebar.open { transform: translateX(0); }
            .sidebar-overlay.open { display: block; }
            .mobile-toggle { display: block; }
            .main-content { margin-left: 0; padding: 24px 16px; padding-top: 64px; }
            .grid-4 { grid-template-columns: 1fr 1fr; }
            .grid-2 { grid-template-columns: 1fr; }
            .grid-3 { grid-template-columns: 1fr; }
            .card-value { font-size: 22px; }
        }

        @media (max-width: 480px) {
            .grid-4 { grid-template-columns: 1fr; }
        }
    </style>
</head>
<body>
    <button class="mobile-toggle" onclick="toggleSidebar()" id="mobileToggle">☰</button>
    <div class="sidebar-overlay" id="sidebarOverlay" onclick="toggleSidebar()"></div>

    <div class="app-layout">
        <!-- Sidebar -->
        <nav class="sidebar" id="sidebar">
            <div class="sidebar-brand">
                <div class="brand-icon">🤖</div>
                <div>
                    <h1>Butler</h1>
                    <small>Personal Assistant</small>
                </div>
            </div>

            <div class="nav-section">Dashboard</div>
            <a href="{{ route('dashboard') }}" class="nav-link {{ request()->routeIs('dashboard') ? 'active' : '' }}">
                <span class="nav-icon">📊</span> Overview
            </a>
            <a href="{{ route('dashboard.spending') }}" class="nav-link {{ request()->routeIs('dashboard.spending') ? 'active' : '' }}">
                <span class="nav-icon">💸</span> Spending
            </a>
            <a href="{{ route('dashboard.nutrition') }}" class="nav-link {{ request()->routeIs('dashboard.nutrition') ? 'active' : '' }}">
                <span class="nav-icon">🍽️</span> Nutrition
            </a>
            <a href="{{ route('dashboard.insights') }}" class="nav-link {{ request()->routeIs('dashboard.insights') ? 'active' : '' }}">
                <span class="nav-icon">💡</span> Insights
            </a>

            <div style="flex: 1;"></div>

            <div style="padding: 16px 12px; border-top: 1px solid var(--border); margin-top: 16px;">
                <p style="font-size: 11px; color: var(--text-muted);">
                    Send messages to your<br>Telegram bot to start tracking!
                </p>
            </div>
        </nav>

        <!-- Main Content -->
        <main class="main-content">
            @yield('content')
        </main>
    </div>

    <script>
        function toggleSidebar() {
            document.getElementById('sidebar').classList.toggle('open');
            document.getElementById('sidebarOverlay').classList.toggle('open');
        }

        // Chart.js global defaults for dark theme
        Chart.defaults.color = '#8888a0';
        Chart.defaults.borderColor = 'rgba(35, 35, 47, 0.8)';
        Chart.defaults.font.family = "'Inter', sans-serif";

        function formatRupiah(num) {
            return 'Rp ' + new Intl.NumberFormat('id-ID').format(num);
        }
    </script>

    @yield('scripts')
</body>
</html>
