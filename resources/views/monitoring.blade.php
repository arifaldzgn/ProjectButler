<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Butler | System Monitoring</title>
    <!-- Google Fonts: Outfit -->
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --bg-color: #0f172a;
            --text-main: #f8fafc;
            --text-muted: #94a3b8;
            --glass-bg: rgba(30, 41, 59, 0.7);
            --glass-border: rgba(255, 255, 255, 0.1);
            --primary: #3b82f6;
            --primary-glow: rgba(59, 130, 246, 0.5);
            --success: #10b981;
            --success-glow: rgba(16, 185, 129, 0.5);
            --danger: #ef4444;
            --danger-glow: rgba(239, 68, 68, 0.5);
            --warning: #f59e0b;
        }

        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        body {
            font-family: 'Outfit', sans-serif;
            background: linear-gradient(135deg, #020617 0%, var(--bg-color) 100%);
            color: var(--text-main);
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            align-items: center;
            padding: 3rem 1rem;
            overflow-x: hidden;
            position: relative;
        }

        /* Ambient Background Glows */
        .ambient-glow {
            position: absolute;
            border-radius: 50%;
            filter: blur(100px);
            z-index: 0;
            opacity: 0.5;
            animation: float 10s infinite ease-in-out alternate;
        }
        .glow-1 {
            top: -10%; left: -10%;
            width: 40vw; height: 40vw;
            background: var(--primary-glow);
        }
        .glow-2 {
            bottom: -10%; right: -10%;
            width: 30vw; height: 30vw;
            background: var(--success-glow);
            animation-delay: -5s;
        }

        @keyframes float {
            0% { transform: translate(0, 0) scale(1); }
            100% { transform: translate(50px, 50px) scale(1.1); }
        }

        .container {
            position: relative;
            z-index: 10;
            width: 100%;
            max-width: 900px;
            display: grid;
            gap: 2rem;
            animation: slideUp 0.8s ease-out forwards;
        }

        @keyframes slideUp {
            from { opacity: 0; transform: translateY(30px); }
            to { opacity: 1; transform: translateY(0); }
        }

        header {
            text-align: center;
            margin-bottom: 2rem;
        }

        h1 {
            font-size: 2.5rem;
            font-weight: 700;
            letter-spacing: -0.02em;
            background: linear-gradient(to right, #60a5fa, #a78bfa);
            -webkit-background-clip: text;
            background-clip: text;
            color: transparent;
            margin-bottom: 0.5rem;
        }

        p.subtitle {
            color: var(--text-muted);
            font-size: 1.1rem;
        }

        .glass-panel {
            background: var(--glass-bg);
            backdrop-filter: blur(16px);
            -webkit-backdrop-filter: blur(16px);
            border: 1px solid var(--glass-border);
            border-radius: 24px;
            padding: 2rem;
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.2);
            transition: transform 0.3s ease, box-shadow 0.3s ease;
        }

        .glass-panel:hover {
            transform: translateY(-5px);
            box-shadow: 0 30px 60px rgba(0, 0, 0, 0.3);
        }

        .panel-title {
            font-size: 1.5rem;
            font-weight: 600;
            margin-bottom: 1.5rem;
            display: flex;
            align-items: center;
            gap: 0.75rem;
            border-bottom: 1px solid var(--glass-border);
            padding-bottom: 1rem;
        }

        .data-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 1.5rem;
        }

        .data-item {
            display: flex;
            flex-direction: column;
            gap: 0.5rem;
        }

        .data-label {
            font-size: 0.85rem;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            color: var(--text-muted);
            font-weight: 500;
        }

        .data-value {
            font-size: 1.1rem;
            font-weight: 500;
            word-break: break-all;
        }

        .status-badge {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.35rem 0.75rem;
            border-radius: 9999px;
            font-size: 0.875rem;
            font-weight: 600;
            background: rgba(255, 255, 255, 0.1);
        }

        .status-badge.online {
            background: rgba(16, 185, 129, 0.15);
            color: var(--success);
            border: 1px solid rgba(16, 185, 129, 0.3);
            box-shadow: 0 0 10px var(--success-glow);
        }

        .status-badge.offline {
            background: rgba(239, 68, 68, 0.15);
            color: var(--danger);
            border: 1px solid rgba(239, 68, 68, 0.3);
            box-shadow: 0 0 10px var(--danger-glow);
        }
        
        .status-badge.warning {
            background: rgba(245, 158, 11, 0.15);
            color: var(--warning);
            border: 1px solid rgba(245, 158, 11, 0.3);
        }

        .indicator {
            width: 8px;
            height: 8px;
            border-radius: 50%;
        }

        .online .indicator {
            background-color: var(--success);
            box-shadow: 0 0 8px var(--success);
            animation: pulse-success 2s infinite;
        }

        .offline .indicator {
            background-color: var(--danger);
            box-shadow: 0 0 8px var(--danger);
        }

        @keyframes pulse-success {
            0% { box-shadow: 0 0 0 0 rgba(16, 185, 129, 0.7); }
            70% { box-shadow: 0 0 0 6px rgba(16, 185, 129, 0); }
            100% { box-shadow: 0 0 0 0 rgba(16, 185, 129, 0); }
        }

        .code-block {
            background: rgba(0, 0, 0, 0.3);
            padding: 1rem;
            border-radius: 12px;
            font-family: monospace;
            font-size: 0.9rem;
            color: #a78bfa;
            border: 1px solid rgba(255, 255, 255, 0.05);
            margin-top: 0.5rem;
        }
    </style>
</head>
<body>
    <div class="ambient-glow glow-1"></div>
    <div class="ambient-glow glow-2"></div>

    <div class="container">
        <header>
            <h1>System Monitoring</h1>
            <p class="subtitle">Live status for Butler AI and Telegram Webhook</p>
        </header>

        <!-- Telegram Webhook Panel -->
        <div class="glass-panel" style="animation-delay: 0.1s;">
            <div class="panel-title">
                <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m22 2-7 20-4-9-9-4Z"/><path d="M22 2 11 13"/></svg>
                Telegram Bot Status
            </div>
            
            <div class="data-grid">
                <div class="data-item">
                    <span class="data-label">Bot Identity</span>
                    <span class="data-value">
                        @if($botInfo && isset($botInfo['username']))
                            {{ '@' . $botInfo['username'] }} ({{ $botInfo['first_name'] }})
                        @else
                            <span style="color: var(--danger);">Unavailable</span>
                        @endif
                    </span>
                </div>

                <div class="data-item">
                    <span class="data-label">Webhook Status</span>
                    <span class="data-value">
                        @if(isset($webhookInfo['error']))
                            <div class="status-badge offline">
                                <div class="indicator"></div> API Error
                            </div>
                        @elseif($webhookInfo && isset($webhookInfo['url']) && !empty($webhookInfo['url']))
                            @if(isset($webhookInfo['last_error_date']))
                                <div class="status-badge warning">
                                    <div class="indicator"></div> Has Errors
                                </div>
                            @else
                                <div class="status-badge online">
                                    <div class="indicator"></div> Active
                                </div>
                            @endif
                        @else
                            <div class="status-badge offline">
                                <div class="indicator"></div> Not Set
                            </div>
                        @endif
                    </span>
                </div>

                <div class="data-item" style="grid-column: 1 / -1;">
                    <span class="data-label">Webhook URL</span>
                    <span class="data-value" style="color: #60a5fa;">
                        {{ $webhookInfo['url'] ?? 'Not configured' }}
                    </span>
                </div>
                
                @if(isset($webhookInfo['last_error_message']) && !empty($webhookInfo['last_error_message']))
                <div class="data-item" style="grid-column: 1 / -1;">
                    <span class="data-label">Last Error</span>
                    <div class="code-block" style="color: var(--danger);">
                        {{ date('Y-m-d H:i:s', $webhookInfo['last_error_date']) }} - {{ $webhookInfo['last_error_message'] }}
                    </div>
                </div>
                @endif
                
                <div class="data-item">
                    <span class="data-label">Pending Updates</span>
                    <span class="data-value">{{ $webhookInfo['pending_update_count'] ?? 0 }}</span>
                </div>
            </div>
        </div>

        <!-- AI Configuration Panel -->
        <div class="glass-panel" style="animation-delay: 0.2s;">
            <div class="panel-title">
                <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 2v20"/><path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/></svg>
                AI Integration
            </div>
            
            <div class="data-grid">
                <div class="data-item">
                    <span class="data-label">Integration Status</span>
                    <span class="data-value">
                        @if($aiStatus === 'Configured')
                            <div class="status-badge online">
                                <div class="indicator"></div> {{ $aiStatus }}
                            </div>
                        @else
                            <div class="status-badge offline">
                                <div class="indicator"></div> {{ $aiStatus }}
                            </div>
                        @endif
                    </span>
                </div>

                <div class="data-item">
                    <span class="data-label">Primary Model</span>
                    <div class="code-block" style="margin-top: 0;">{{ $aiConfig['primary_model'] }}</div>
                </div>

                <div class="data-item">
                    <span class="data-label">Fallback Model</span>
                    <div class="code-block" style="margin-top: 0; color: var(--text-muted);">{{ $aiConfig['fallback_model'] }}</div>
                </div>

                <div class="data-item" style="grid-column: 1 / -1;">
                    <span class="data-label">Base URL</span>
                    <span class="data-value" style="color: #60a5fa;">{{ $aiConfig['base_url'] }}</span>
                </div>
            </div>
        </div>
        
        <!-- App Details Panel -->
        <div class="glass-panel" style="animation-delay: 0.3s; padding: 1.5rem;">
            <div class="data-grid" style="grid-template-columns: 1fr 1fr; gap: 1rem;">
                <div class="data-item">
                    <span class="data-label">Application URL</span>
                    <span class="data-value" style="font-size: 0.95rem;">{{ $appUrl }}</span>
                </div>
                <div class="data-item">
                    <span class="data-label">Timezone</span>
                    <span class="data-value" style="font-size: 0.95rem;">{{ $timezone }}</span>
                </div>
            </div>
        </div>

    </div>
</body>
</html>
