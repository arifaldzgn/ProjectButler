<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="theme-color" content="#0a0a0a">
    <title>Pasang Butler Shortcut</title>
    <style>
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

        :root {
            --bg:          #0a0a0a;
            --bg-card:     #141414;
            --bg-elevated: #1c1c1c;
            --border:      rgba(255,255,255,0.08);
            --text:        #f5f5f5;
            --text-muted:  #888;
            --text-dim:    #444;
            --accent:      #ffffff;
            --success:     #22c55e;
            --warning:     #f59e0b;
            --radius:      14px;
            --radius-sm:   10px;
        }

        html, body {
            background: var(--bg);
            color: var(--text);
            font-family: -apple-system, 'Inter', system-ui, sans-serif;
            font-size: 16px;
            line-height: 1.55;
            min-height: 100dvh;
            -webkit-font-smoothing: antialiased;
        }

        .page {
            max-width: 420px;
            margin: 0 auto;
            padding: env(safe-area-inset-top, 20px) 24px env(safe-area-inset-bottom, 40px);
        }

        /* ── Header ── */
        .header {
            padding-top: 40px;
            padding-bottom: 32px;
        }
        .wordmark {
            font-size: 11px;
            font-weight: 700;
            letter-spacing: 0.14em;
            text-transform: uppercase;
            color: var(--text-dim);
            margin-bottom: 18px;
        }
        .header h1 {
            font-size: 28px;
            font-weight: 700;
            letter-spacing: -0.03em;
            line-height: 1.2;
        }
        .header p {
            font-size: 15px;
            color: var(--text-muted);
            margin-top: 8px;
        }

        /* ── Code block ── */
        .code-section {
            background: var(--bg-card);
            border: 1px solid var(--border);
            border-radius: var(--radius);
            padding: 24px;
            margin-bottom: 28px;
            text-align: center;
        }
        .code-label {
            font-size: 11px;
            font-weight: 600;
            letter-spacing: 0.12em;
            text-transform: uppercase;
            color: var(--text-dim);
            margin-bottom: 14px;
        }
        .pairing-code {
            font-size: 42px;
            font-weight: 700;
            letter-spacing: 0.18em;
            font-variant-numeric: tabular-nums;
            color: var(--text);
            font-family: 'SF Mono', 'Fira Code', monospace;
        }
        .code-expiry {
            font-size: 12px;
            color: var(--text-muted);
            margin-top: 10px;
        }
        .code-expiry.expired { color: var(--warning); }

        .copy-btn {
            display: inline-flex;
            align-items: center;
            gap: 7px;
            margin-top: 16px;
            padding: 9px 20px;
            background: var(--bg-elevated);
            border: 1px solid var(--border);
            border-radius: 100px;
            font-size: 13px;
            font-weight: 500;
            color: var(--text-muted);
            cursor: pointer;
            transition: all 0.15s;
            -webkit-tap-highlight-color: transparent;
        }
        .copy-btn:active { transform: scale(0.97); }
        .copy-btn.copied { color: var(--success); border-color: rgba(34,197,94,0.3); }

        /* ── Install button ── */
        .install-btn {
            display: block;
            width: 100%;
            padding: 16px;
            background: var(--text);
            color: var(--bg);
            border: none;
            border-radius: var(--radius-sm);
            font-size: 16px;
            font-weight: 600;
            text-align: center;
            text-decoration: none;
            cursor: pointer;
            transition: opacity 0.15s;
            margin-bottom: 12px;
            -webkit-tap-highlight-color: transparent;
        }
        .install-btn:active { opacity: 0.85; }

        .manual-note {
            font-size: 13px;
            color: var(--text-dim);
            text-align: center;
            margin-bottom: 32px;
        }
        .manual-note a { color: var(--text-muted); text-decoration: underline; text-underline-offset: 2px; }

        /* ── Steps ── */
        .steps-heading {
            font-size: 12px;
            font-weight: 600;
            letter-spacing: 0.1em;
            text-transform: uppercase;
            color: var(--text-dim);
            margin-bottom: 14px;
        }
        .step {
            display: flex;
            gap: 14px;
            padding: 16px 0;
            border-bottom: 1px solid var(--border);
        }
        .step:last-child { border-bottom: none; }
        .step-num {
            width: 26px;
            height: 26px;
            border-radius: 50%;
            background: var(--bg-elevated);
            border: 1px solid var(--border);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 12px;
            font-weight: 600;
            color: var(--text-muted);
            flex-shrink: 0;
            margin-top: 2px;
        }
        .step-body { flex: 1; }
        .step-title { font-weight: 600; font-size: 15px; margin-bottom: 3px; }
        .step-desc  { font-size: 13px; color: var(--text-muted); }
        .step-code  {
            display: inline-block;
            font-family: 'SF Mono', 'Fira Code', monospace;
            background: var(--bg-elevated);
            border: 1px solid var(--border);
            border-radius: 6px;
            padding: 2px 8px;
            font-size: 13px;
            margin-top: 4px;
            letter-spacing: 0.08em;
        }

        /* ── Footer ── */
        .footer {
            padding-top: 36px;
            font-size: 12px;
            color: var(--text-dim);
            text-align: center;
            line-height: 1.7;
        }
    </style>
</head>
<body>
<div class="page">

    <div class="header">
        <div class="wordmark">Butler</div>
        <h1>Pasang iPhone Shortcut</h1>
        <p>Kamu bisa mencatat pengeluaran, makanan, dan pengingat dengan satu ketukan — atau lewat Siri.</p>
    </div>

    {{-- Pairing code block --}}
    <div class="code-section">
        <div class="code-label">Kode Pairing Kamu</div>
        <div class="pairing-code" id="pairingCode">{{ $code }}</div>

        @if($expiresAt)
        <div class="code-expiry" id="expiryLabel">
            Berlaku hingga {{ $expiresAt->format('H:i') }} WIB
        </div>
        @endif

        <button class="copy-btn" onclick="copyCode()" id="copyBtn">
            <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2">
                <rect x="9" y="9" width="13" height="13" rx="2"/><path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"/>
            </svg>
            Salin kode
        </button>
    </div>

    {{-- Install button --}}
    @if(config('butler.shortcut.install_url'))
    <a href="{{ config('butler.shortcut.install_url') }}" class="install-btn">
        Instal Shortcut Butler
    </a>
    <p class="manual-note">Ketuk untuk buka Shortcuts.app dan instal.</p>
    @else
    <a href="https://apps.apple.com/us/app/shortcuts/id915249334" class="install-btn" target="_blank">
        Buka Shortcuts.app
    </a>
    <p class="manual-note">Lihat panduan buat shortcut di bawah &darr;</p>
    @endif

    {{-- Steps --}}
    <div class="steps-heading">Cara pasang</div>

    <div class="step">
        <div class="step-num">1</div>
        <div class="step-body">
            <div class="step-title">Instal Shortcut</div>
            <div class="step-desc">Ketuk tombol di atas untuk instal Shortcut Butler ke iPhone-mu.</div>
        </div>
    </div>

    <div class="step">
        <div class="step-num">2</div>
        <div class="step-body">
            <div class="step-title">Jalankan Shortcut</div>
            <div class="step-desc">Buka app Shortcuts → ketuk <strong>Butler</strong>.</div>
        </div>
    </div>

    <div class="step">
        <div class="step-num">3</div>
        <div class="step-body">
            <div class="step-title">Masukkan kode pairing</div>
            <div class="step-desc">
                Saat diminta, ketuk <strong>Salin kode</strong> di atas lalu paste di Shortcut.
                <br><span class="step-code" id="codeInline">{{ $code }}</span>
            </div>
        </div>
    </div>

    <div class="step">
        <div class="step-num">4</div>
        <div class="step-body">
            <div class="step-title">Selesai!</div>
            <div class="step-desc">Shortcut terhubung ke akun Telegram-mu secara otomatis. Coba bilang <em>"Hey Siri, Butler"</em>.</div>
        </div>
    </div>

    <div class="footer">
        Kode berlaku sekali &middot; Minta kode baru lewat <strong>/pair_iphone</strong> di Telegram
        @if($expiresAt)
        <br>Kode ini kedaluwarsa {{ $expiresAt->diffForHumans() }}
        @endif
    </div>

</div>

<script>
function copyCode() {
    const code = document.getElementById('pairingCode').innerText.trim();
    navigator.clipboard.writeText(code).then(() => {
        const btn = document.getElementById('copyBtn');
        btn.classList.add('copied');
        btn.innerHTML = `<svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="20 6 9 17 4 12"/></svg> Disalin!`;
        setTimeout(() => {
            btn.classList.remove('copied');
            btn.innerHTML = `<svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><rect x="9" y="9" width="13" height="13" rx="2"/><path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"/></svg> Salin kode`;
        }, 2500);
    }).catch(() => {
        // Fallback for older iOS
        const el = document.createElement('textarea');
        el.value = code;
        el.style.position = 'fixed';
        el.style.opacity = '0';
        document.body.appendChild(el);
        el.focus(); el.select();
        document.execCommand('copy');
        document.body.removeChild(el);

        const btn = document.getElementById('copyBtn');
        btn.classList.add('copied');
        btn.textContent = 'Disalin!';
        setTimeout(() => {
            btn.classList.remove('copied');
            btn.textContent = 'Salin kode';
        }, 2500);
    });
}

// Show expired state if code is past its time
@if($expiresAt)
const expiresAt = new Date({{ $expiresAt->timestamp }} * 1000);
function checkExpiry() {
    if (new Date() > expiresAt) {
        const el = document.getElementById('expiryLabel');
        if (el) {
            el.classList.add('expired');
            el.textContent = 'Kode sudah kedaluwarsa. Minta kode baru via /pair_iphone di Telegram.';
        }
    }
}
checkExpiry();
setInterval(checkExpiry, 30000);
@endif
</script>
</body>
</html>
