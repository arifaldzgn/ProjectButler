<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Project Butler — AI System Architecture</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&family=JetBrains+Mono:wght@400;500&display=swap" rel="stylesheet">
    <style>
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

        :root {
            --bg:           #07070f;
            --bg-card:      #0d0d1a;
            --bg-diagram:   #060610;
            --bg-nav:       rgba(7, 7, 15, 0.85);
            --border:       rgba(139, 92, 246, 0.1);
            --border-hover: rgba(139, 92, 246, 0.3);
            --text:         #f1f5f9;
            --text-muted:   #94a3b8;
            --text-dim:     #475569;
            --purple:       #7c3aed;
            --blue:         #3b82f6;
            --amber:        #f59e0b;
            --green:        #10b981;
            --pink:         #ec4899;
            --radius:       14px;
            --font:         'Inter', system-ui, sans-serif;
            --mono:         'JetBrains Mono', monospace;
        }

        html { scroll-behavior: smooth; }

        body {
            background: var(--bg);
            color: var(--text);
            font-family: var(--font);
            line-height: 1.6;
            min-height: 100vh;
        }

        /* ── NAV ──────────────────────────────────────────── */
        nav {
            position: sticky;
            top: 0;
            z-index: 100;
            height: 58px;
            background: var(--bg-nav);
            backdrop-filter: blur(24px);
            -webkit-backdrop-filter: blur(24px);
            border-bottom: 1px solid var(--border);
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 0 32px;
            gap: 16px;
        }

        .nav-logo {
            display: flex;
            align-items: center;
            gap: 10px;
            font-weight: 700;
            font-size: 15px;
            color: var(--text);
            text-decoration: none;
            white-space: nowrap;
        }

        .nav-logo-dot {
            width: 8px;
            height: 8px;
            border-radius: 50%;
            background: var(--purple);
            box-shadow: 0 0 8px var(--purple);
        }

        .nav-links {
            display: flex;
            align-items: center;
            gap: 4px;
            list-style: none;
            flex-wrap: wrap;
        }

        .nav-links a {
            padding: 5px 12px;
            border-radius: 8px;
            font-size: 12px;
            font-weight: 500;
            color: var(--text-muted);
            text-decoration: none;
            transition: background .15s, color .15s;
            white-space: nowrap;
        }

        .nav-links a:hover { background: rgba(139,92,246,.08); color: var(--text); }

        .nav-badge {
            padding: 4px 12px;
            border-radius: 999px;
            font-size: 11px;
            font-weight: 600;
            background: rgba(124,58,237,.12);
            color: var(--purple);
            border: 1px solid rgba(124,58,237,.2);
            white-space: nowrap;
        }

        /* ── HERO ─────────────────────────────────────────── */
        .hero {
            text-align: center;
            padding: 80px 24px 64px;
            background: radial-gradient(ellipse 80% 50% at 50% -10%, rgba(124,58,237,.18) 0%, transparent 70%);
        }

        .hero-eyebrow {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 5px 14px;
            border-radius: 999px;
            border: 1px solid rgba(124,58,237,.25);
            background: rgba(124,58,237,.08);
            font-size: 11px;
            font-weight: 600;
            letter-spacing: .06em;
            text-transform: uppercase;
            color: var(--purple);
            margin-bottom: 24px;
        }

        .hero h1 {
            font-size: clamp(32px, 5vw, 52px);
            font-weight: 800;
            letter-spacing: -.02em;
            line-height: 1.1;
            margin-bottom: 12px;
            background: linear-gradient(135deg, #f1f5f9 30%, #a78bfa);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        .hero-sub {
            font-size: 15px;
            color: var(--text-muted);
            max-width: 520px;
            margin: 0 auto 32px;
        }

        .hero-pills {
            display: flex;
            justify-content: center;
            gap: 10px;
            flex-wrap: wrap;
        }

        .hero-pill {
            display: flex;
            align-items: center;
            gap: 6px;
            padding: 6px 14px;
            border-radius: 999px;
            font-size: 12px;
            font-weight: 500;
            border: 1px solid var(--border);
            background: var(--bg-card);
            color: var(--text-muted);
        }

        .hero-pill span { font-size: 14px; }

        /* ── INDEX ────────────────────────────────────────── */
        .index-bar {
            max-width: 900px;
            margin: 0 auto 56px;
            padding: 0 24px;
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(160px, 1fr));
            gap: 10px;
        }

        .index-item {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 12px 16px;
            border-radius: var(--radius);
            border: 1px solid var(--border);
            background: var(--bg-card);
            text-decoration: none;
            transition: border-color .2s, transform .2s;
        }

        .index-item:hover { border-color: var(--border-hover); transform: translateY(-1px); }

        .index-num {
            font-size: 10px;
            font-weight: 700;
            font-family: var(--mono);
            width: 22px;
            height: 22px;
            border-radius: 6px;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
        }

        .index-label { font-size: 12px; font-weight: 600; color: var(--text); }
        .index-sub   { font-size: 11px; color: var(--text-dim); }

        /* ── SECTION ──────────────────────────────────────── */
        .section {
            max-width: 1000px;
            margin: 0 auto 48px;
            padding: 0 24px;
            scroll-margin-top: 80px;
        }

        .card {
            background: var(--bg-card);
            border: 1px solid var(--border);
            border-radius: var(--radius);
            overflow: hidden;
            transition: border-color .25s;
        }

        .card:hover { border-color: var(--border-hover); }

        .card-header {
            padding: 22px 28px;
            border-bottom: 1px solid var(--border);
            display: flex;
            align-items: flex-start;
            gap: 16px;
        }

        .section-tag {
            font-size: 10px;
            font-weight: 700;
            font-family: var(--mono);
            padding: 4px 10px;
            border-radius: 6px;
            margin-top: 2px;
            white-space: nowrap;
            flex-shrink: 0;
        }

        .card-header h2 {
            font-size: 17px;
            font-weight: 700;
            color: var(--text);
            margin-bottom: 3px;
        }

        .card-header p {
            font-size: 13px;
            color: var(--text-muted);
        }

        /* ── DIAGRAM AREA ─────────────────────────────────── */
        .diagram-area {
            padding: 40px 24px;
            background: var(--bg-diagram);
            overflow-x: auto;
            display: flex;
            justify-content: center;
            align-items: flex-start;
            min-height: 180px;
            border-bottom: 1px solid var(--border);
        }

        .diagram-area .mermaid {
            min-width: min-content;
        }

        /* ── INSIGHT ──────────────────────────────────────── */
        .insight {
            padding: 16px 28px;
            display: flex;
            align-items: flex-start;
            gap: 12px;
            font-size: 13px;
            color: var(--text-muted);
            line-height: 1.65;
        }

        .insight-icon {
            font-size: 16px;
            margin-top: 1px;
            flex-shrink: 0;
        }

        .insight strong { color: var(--text); font-weight: 600; }

        /* ── TAGS ─────────────────────────────────────────── */
        .tags {
            padding: 12px 28px 16px;
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
            border-top: 1px solid var(--border);
        }

        .tag {
            font-size: 11px;
            font-weight: 500;
            padding: 3px 10px;
            border-radius: 999px;
            border: 1px solid;
        }

        /* ── FOOTER ───────────────────────────────────────── */
        footer {
            max-width: 1000px;
            margin: 0 auto;
            padding: 40px 24px 60px;
            border-top: 1px solid var(--border);
            display: flex;
            align-items: center;
            justify-content: space-between;
            flex-wrap: wrap;
            gap: 12px;
        }

        footer p { font-size: 13px; color: var(--text-dim); }

        .footer-stack {
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
        }

        .stack-pill {
            font-size: 11px;
            font-family: var(--mono);
            padding: 3px 10px;
            border-radius: 6px;
            background: rgba(255,255,255,.04);
            border: 1px solid var(--border);
            color: var(--text-dim);
        }

        /* ── DIVIDER ──────────────────────────────────────── */
        .section-divider {
            max-width: 1000px;
            margin: 0 auto 48px;
            padding: 0 24px;
            display: flex;
            align-items: center;
            gap: 16px;
            color: var(--text-dim);
            font-size: 11px;
            letter-spacing: .06em;
            text-transform: uppercase;
            font-weight: 600;
        }

        .section-divider::before,
        .section-divider::after {
            content: '';
            flex: 1;
            height: 1px;
            background: var(--border);
        }

        /* ── RESPONSIVE ───────────────────────────────────── */
        @media (max-width: 600px) {
            nav { padding: 0 16px; }
            .nav-links { display: none; }
            .hero { padding: 56px 16px 48px; }
            .section { padding: 0 16px; }
            .card-header { padding: 18px 20px; flex-direction: column; gap: 8px; }
            .insight { padding: 14px 20px; }
            .tags { padding: 12px 20px 14px; }
            .diagram-area { padding: 24px 12px; }
        }
    </style>
</head>
<body>

{{-- ── NAV ──────────────────────────────────────────────────────── --}}
<nav>
    <a href="#" class="nav-logo">
        <div class="nav-logo-dot"></div>
        Project Butler
    </a>
    <ul class="nav-links">
        <li><a href="#approach">Approach</a></li>
        <li><a href="#behavioral">Behavioral</a></li>
        <li><a href="#memory">Memory</a></li>
        <li><a href="#learning">Learning</a></li>
        <li><a href="#fullloop">Full Loop</a></li>
    </ul>
    <div class="nav-badge">System Architecture</div>
</nav>

{{-- ── HERO ─────────────────────────────────────────────────────── --}}
<section class="hero">
    <div class="hero-eyebrow">
        <span>🎓</span> Apple Developer Academy — Portfolio
    </div>
    <h1>Project Butler<br>AI System Architecture</h1>
    <p class="hero-sub">
        An AI-powered personal assistant that learns how you think about money —
        and gradually gets out of your way.
    </p>
    <div class="hero-pills">
        <div class="hero-pill"><span>🤖</span> Behavioral Learning</div>
        <div class="hero-pill"><span>🧠</span> Persistent Memory</div>
        <div class="hero-pill"><span>⚡</span> Dynamic Commands</div>
        <div class="hero-pill"><span>📱</span> Telegram-first</div>
    </div>
</section>

{{-- ── INDEX ────────────────────────────────────────────────────── --}}
<div class="index-bar">

    <a href="#approach" class="index-item">
        <div class="index-num" style="background:rgba(124,58,237,.15);color:#a78bfa">01</div>
        <div>
            <div class="index-label">The Approach</div>
            <div class="index-sub">Separation of concerns</div>
        </div>
    </a>

    <a href="#behavioral" class="index-item">
        <div class="index-num" style="background:rgba(59,130,246,.15);color:#93c5fd">02</div>
        <div>
            <div class="index-label">Behavioral System</div>
            <div class="index-sub">Smart gate routing</div>
        </div>
    </a>

    <a href="#memory" class="index-item">
        <div class="index-num" style="background:rgba(245,158,11,.15);color:#fcd34d">03</div>
        <div>
            <div class="index-label">Memory</div>
            <div class="index-sub">Pattern storage</div>
        </div>
    </a>

    <a href="#learning" class="index-item">
        <div class="index-num" style="background:rgba(16,185,129,.15);color:#6ee7b7">04</div>
        <div>
            <div class="index-label">Learning Loop</div>
            <div class="index-sub">Confidence stages</div>
        </div>
    </a>

    <a href="#fullloop" class="index-item">
        <div class="index-num" style="background:rgba(236,72,153,.15);color:#f9a8d4">05</div>
        <div>
            <div class="index-label">Full Loop</div>
            <div class="index-sub">End-to-end cycle</div>
        </div>
    </a>

</div>

{{-- ══════════════════════════════════════════════════════════════ --}}
{{-- DIAGRAM 01 — THE APPROACH                                      --}}
{{-- ══════════════════════════════════════════════════════════════ --}}

<section class="section" id="approach">
    <div class="card" style="border-left: 3px solid var(--purple)">

        <div class="card-header">
            <div class="section-tag" style="background:rgba(124,58,237,.12);color:#a78bfa">01</div>
            <div>
                <h2>The Approach — Separation of Concerns</h2>
                <p>AI translates. Backend decides. Always. The two layers never swap roles.</p>
            </div>
        </div>

        <div class="diagram-area">
            @verbatim
            <pre class="mermaid">
flowchart LR
    classDef user     fill:#064e3b,stroke:#10b981,color:#d1fae5
    classDef ai       fill:#3b0764,stroke:#7c3aed,color:#e9d5ff
    classDef backend  fill:#1e3a5f,stroke:#3b82f6,color:#bfdbfe
    classDef db       fill:#451a03,stroke:#f59e0b,color:#fde68a

    USER(["👤 User\n'grab 23k'"]):::user

    subgraph AI_LAYER["  🤖  AI Layer — Translation Only  "]
        A1["Read natural language"]:::ai
        A2["Detect intent\n& confidence score"]:::ai
        A3["Extract entities\namount · merchant · category"]:::ai
        A1 --> A2 --> A3
    end

    subgraph BACKEND["  ⚙️  Backend — All Decisions  "]
        B1["Validate &\napply rules"]:::backend
        B2["Route to\nfund / account"]:::backend
        B3["Create\npending entry"]:::backend
        B1 --> B2 --> B3
    end

    DB[("💾 Database")]:::db
    CONF(["👤 ✅ Confirm\nor ❌ Cancel"]):::user

    USER -->|"casual text"| AI_LAYER
    AI_LAYER -->|"structured JSON\nintent + data"| BACKEND
    BACKEND --> CONF
    CONF -->|"confirmed"| DB
            </pre>
            @endverbatim
        </div>

        <div class="insight">
            <div class="insight-icon">💡</div>
            <div>
                <strong>Why this matters:</strong> AI is probabilistic — it can be wrong. Finance is consequential — wrong is costly.
                Keeping AI strictly as a translator means the backend stays accurate and deterministic
                even when the model isn't 100% sure. The user always confirms before anything is written.
            </div>
        </div>

        <div class="tags">
            <span class="tag" style="border-color:rgba(124,58,237,.3);color:#a78bfa">Deterministic Backend</span>
            <span class="tag" style="border-color:rgba(124,58,237,.3);color:#a78bfa">AI as Translator</span>
            <span class="tag" style="border-color:rgba(124,58,237,.3);color:#a78bfa">Pending Entry Lifecycle</span>
            <span class="tag" style="border-color:rgba(124,58,237,.3);color:#a78bfa">Confidence Score</span>
        </div>

    </div>
</section>

{{-- ══════════════════════════════════════════════════════════════ --}}
{{-- DIAGRAM 02 — BEHAVIORAL SYSTEM                                 --}}
{{-- ══════════════════════════════════════════════════════════════ --}}

<section class="section" id="behavioral">
    <div class="card" style="border-left: 3px solid var(--blue)">

        <div class="card-header">
            <div class="section-tag" style="background:rgba(59,130,246,.12);color:#93c5fd">02</div>
            <div>
                <h2>Behavioral System — Smart Gate Routing</h2>
                <p>Every message passes through a decision filter. AI is only called when nothing else can handle it.</p>
            </div>
        </div>

        <div class="diagram-area">
            @verbatim
            <pre class="mermaid">
flowchart TD
    classDef start    fill:#064e3b,stroke:#10b981,color:#d1fae5
    classDef gate     fill:#1c1917,stroke:#57534e,color:#d6d3d1
    classDef handler  fill:#1e3a5f,stroke:#3b82f6,color:#bfdbfe
    classDef ai       fill:#3b0764,stroke:#7c3aed,color:#e9d5ff
    classDef yes      fill:#064e3b,stroke:#10b981,color:#d1fae5
    classDef no       fill:#450a0a,stroke:#ef4444,color:#fecaca

    MSG(["📨 Message arrives"]):::start

    MSG --> G1{"📷 Is it a photo?"}:::gate
    G1 -->|"Yes"| PHOTO["🧾 Receipt Scanning\nVision AI flow"]:::handler
    G1 -->|"No"| G2

    G2{"🔢 Calorie correction\nin progress?"}:::gate
    G2 -->|"Yes"| CAL["Update calories\ndirectly — no AI"]:::handler
    G2 -->|"No"| G3

    G3{"⚡ Matches a\nquick command?"}:::gate
    G3 -->|"Yes"| CMD["Direct handler\nskip AI entirely"]:::handler
    G3 -->|"No"| AI

    AI["🤖 AI Parser\nparse intent + extract data"]:::ai
    AI --> PENDING["Create Pending Entry\nstatus: unconfirmed"]:::handler

    PENDING --> DEC{"👤 User responds"}:::gate
    DEC -->|"✅ Confirm"| SAVE["Saved\nMemory updated"]:::yes
    DEC -->|"❌ Cancel"| DEL["Discarded\nno trace left"]:::no
            </pre>
            @endverbatim
        </div>

        <div class="insight">
            <div class="insight-icon">💡</div>
            <div>
                <strong>Why this matters:</strong> AI calls cost time and money. The gate system handles cheap operations instantly —
                quick commands, in-progress corrections, photo messages — and only escalates to the AI
                when genuinely needed. The result is a faster, cheaper, more reliable system.
            </div>
        </div>

        <div class="tags">
            <span class="tag" style="border-color:rgba(59,130,246,.3);color:#93c5fd">Gate Pattern</span>
            <span class="tag" style="border-color:rgba(59,130,246,.3);color:#93c5fd">Quick Commands</span>
            <span class="tag" style="border-color:rgba(59,130,246,.3);color:#93c5fd">Receipt Scanning</span>
            <span class="tag" style="border-color:rgba(59,130,246,.3);color:#93c5fd">Calorie Correction State</span>
        </div>

    </div>
</section>

{{-- ══════════════════════════════════════════════════════════════ --}}
{{-- DIAGRAM 03 — MEMORY                                            --}}
{{-- ══════════════════════════════════════════════════════════════ --}}

<section class="section" id="memory">
    <div class="card" style="border-left: 3px solid var(--amber)">

        <div class="card-header">
            <div class="section-tag" style="background:rgba(245,158,11,.12);color:#fcd34d">03</div>
            <div>
                <h2>Memory — Structured Pattern Storage</h2>
                <p>Butler doesn't store chat history. It stores what it has learned <em>about you</em> — domain by domain, with confidence scores.</p>
            </div>
        </div>

        <div class="diagram-area">
            @verbatim
            <pre class="mermaid">
flowchart TD
    classDef root     fill:#451a03,stroke:#f59e0b,color:#fde68a
    classDef domain   fill:#1c1917,stroke:#78716c,color:#e7e5e4
    classDef low      fill:#450a0a,stroke:#ef4444,color:#fecaca
    classDef mid      fill:#422006,stroke:#f97316,color:#fed7aa
    classDef high     fill:#064e3b,stroke:#10b981,color:#d1fae5

    MEM(["🧠 Behavioral Memory"]):::root

    MEM --> D1["merchant_account\n'Grab' → always transport\npaid from GoPay"]:::domain
    MEM --> D2["food_calories\n'nasi goreng' ≈ 600 kcal\nconfirmed 8× by user"]:::domain
    MEM --> D3["meal_timing\n'makan siang' → 12:00–13:00\nhigh confidence"]:::domain
    MEM --> D4["category_account\n'transport' → e-wallet\nnot debit card"]:::domain

    D1 & D2 & D3 & D4 --> SCORE{"Confidence\nScore"}

    SCORE -->|"score < 0.5"| ASK["Ask user explicitly\n'Which account?'"]:::low
    SCORE -->|"score 0.5–0.8"| SUG["Suggest with one tap\n'From GoPay? ✅ / ❌'"]:::mid
    SCORE -->|"score > 0.8"| AUTO["Apply automatically\nnoted quietly in message"]:::high
            </pre>
            @endverbatim
        </div>

        <div class="insight">
            <div class="insight-icon">💡</div>
            <div>
                <strong>Why this matters:</strong> Every memory entry is visible and deletable from the dashboard.
                Users can see exactly what Butler has learned — which merchant maps to which account,
                which food has which calorie estimate. <strong>The learning happens in the open, not in a black box.</strong>
                Trust is built through transparency, not magic.
            </div>
        </div>

        <div class="tags">
            <span class="tag" style="border-color:rgba(245,158,11,.3);color:#fcd34d">Domain-based Storage</span>
            <span class="tag" style="border-color:rgba(245,158,11,.3);color:#fcd34d">Confidence Scoring</span>
            <span class="tag" style="border-color:rgba(245,158,11,.3);color:#fcd34d">Transparent Memory</span>
            <span class="tag" style="border-color:rgba(245,158,11,.3);color:#fcd34d">User-deletable Patterns</span>
        </div>

    </div>
</section>

{{-- ══════════════════════════════════════════════════════════════ --}}
{{-- DIAGRAM 04 — LEARNING LOOP / STATE MACHINE                     --}}
{{-- ══════════════════════════════════════════════════════════════ --}}

<section class="section" id="learning">
    <div class="card" style="border-left: 3px solid var(--green)">

        <div class="card-header">
            <div class="section-tag" style="background:rgba(16,185,129,.12);color:#6ee7b7">04</div>
            <div>
                <h2>Learning Loop — Confidence State Machine</h2>
                <p>Butler earns the right to act without asking. Trust is built through a track record, not assumed upfront.</p>
            </div>
        </div>

        <div class="diagram-area">
            @verbatim
            <pre class="mermaid">
stateDiagram-v2
    direction LR

    [*] --> Observe : First time\npattern seen

    Observe --> Observe  : User answers\nconfidence stays low
    Observe --> Suggest  : Consistent behavior\nconfidence > 0.5

    Suggest --> Suggest  : User confirms\nconfidence grows
    Suggest --> Apply    : High confidence\nthreshold > 0.8

    Suggest --> Observe  : User keeps\ncorrecting Butler
    Apply --> Suggest    : User corrects\ntrust dips once

    Apply --> Apply      : User keeps\nconfirming

    note right of Observe
        Butler asks every time.
        "Which account did you use?"
    end note

    note right of Suggest
        Butler proposes an answer.
        "From GoPay? ✅ / ❌"
    end note

    note right of Apply
        Butler fills it in silently.
        Just notes it in the message.
    end note
            </pre>
            @endverbatim
        </div>

        <div class="insight">
            <div class="insight-icon">💡</div>
            <div>
                <strong>Why this matters:</strong> Most apps are static from day one — they ask the same questions forever.
                Butler's three-stage model means the <strong>daily friction of using it decreases over time</strong> as
                confidence accumulates through real usage. The longer you use it, the less it gets in your way.
                That's the core retention mechanic.
            </div>
        </div>

        <div class="tags">
            <span class="tag" style="border-color:rgba(16,185,129,.3);color:#6ee7b7">Observe Stage</span>
            <span class="tag" style="border-color:rgba(16,185,129,.3);color:#6ee7b7">Suggest Stage</span>
            <span class="tag" style="border-color:rgba(16,185,129,.3);color:#6ee7b7">Apply Stage</span>
            <span class="tag" style="border-color:rgba(16,185,129,.3);color:#6ee7b7">Confidence Decay on Correction</span>
        </div>

    </div>
</section>

{{-- ══════════════════════════════════════════════════════════════ --}}
{{-- DIAGRAM 05 — FULL LOOP                                         --}}
{{-- ══════════════════════════════════════════════════════════════ --}}

<section class="section" id="fullloop">
    <div class="card" style="border-left: 3px solid var(--pink)">

        <div class="card-header">
            <div class="section-tag" style="background:rgba(236,72,153,.12);color:#f9a8d4">05</div>
            <div>
                <h2>Full Loop — End-to-End Feedback Cycle</h2>
                <p>Every message → every confirmation → every correction feeds back into memory. The system improves itself through normal daily use.</p>
            </div>
        </div>

        <div class="diagram-area">
            @verbatim
            <pre class="mermaid">
flowchart TD
    classDef user    fill:#064e3b,stroke:#10b981,color:#d1fae5
    classDef memory  fill:#451a03,stroke:#f59e0b,color:#fde68a
    classDef ai      fill:#3b0764,stroke:#7c3aed,color:#e9d5ff
    classDef backend fill:#1e3a5f,stroke:#3b82f6,color:#bfdbfe
    classDef yes     fill:#064e3b,stroke:#10b981,color:#d1fae5
    classDef no      fill:#7f1d1d,stroke:#ef4444,color:#fecaca

    MSG(["💬 User sends message"]):::user

    MSG --> MEMCHECK["🧠 Check Behavioral\nMemory for known pattern"]:::memory
    MEMCHECK --> KNOWN{"Pattern\nfound?"}

    KNOWN -->|"Yes"| STAGE{"Confidence\nlevel?"}
    KNOWN -->|"No"| AI["🤖 AI Parser\nfigure out intent from scratch"]:::ai

    STAGE -->|"Low\nAsk"| ASK["Ask user\nfor clarification"]:::backend
    STAGE -->|"Mid\nSuggest"| SUG["Propose answer\nwith ✅ / ❌"]:::backend
    STAGE -->|"High\nApply"| AUTO["Fill in silently\nnote in message"]:::backend

    AI    --> CREATE
    ASK   --> CREATE
    SUG   --> CREATE
    AUTO  --> CREATE

    CREATE["Create Pending Entry"]:::backend

    CREATE --> ACTION{"👤 User\nresponds"}

    ACTION -->|"✅ Confirm"| POS["Confidence rises\npattern reinforced"]:::yes
    ACTION -->|"❌ Correct"| NEG["Confidence drops\npattern revised"]:::no

    POS --> WRITE[("💾 Memory updated")]:::memory
    NEG --> WRITE

    WRITE --> NEXT(["Next interaction\nis smarter ↑"]):::user
            </pre>
            @endverbatim
        </div>

        <div class="insight">
            <div class="insight-icon">💡</div>
            <div>
                <strong>The one-line summary:</strong> Every ✅ tap is a training signal.
                Every ❌ correction is a correction signal. The model isn't trained on a generic dataset —
                <strong>it's trained on you, in real time, through normal daily use.</strong>
                No setup. No preferences screen. Just usage.
            </div>
        </div>

        <div class="tags">
            <span class="tag" style="border-color:rgba(236,72,153,.3);color:#f9a8d4">Closed Feedback Loop</span>
            <span class="tag" style="border-color:rgba(236,72,153,.3);color:#f9a8d4">Real-time Personalization</span>
            <span class="tag" style="border-color:rgba(236,72,153,.3);color:#f9a8d4">Signal from Confirmation</span>
            <span class="tag" style="border-color:rgba(236,72,153,.3);color:#f9a8d4">Signal from Correction</span>
        </div>

    </div>
</section>

{{-- ── FOOTER ─────────────────────────────────────────────────── --}}
<footer>
    <div>
        <p style="font-weight:600;color:var(--text-muted);margin-bottom:4px">Project Butler v2.0 — Self-initiated individual project</p>
        <p>Apple Developer Academy Portfolio · {{ date('Y') }}</p>
    </div>
    <div class="footer-stack">
        <span class="stack-pill">Laravel 12</span>
        <span class="stack-pill">PostgreSQL</span>
        <span class="stack-pill">Telegram Bot API</span>
        <span class="stack-pill">OpenRouter</span>
        <span class="stack-pill">Gemini / Claude</span>
        <span class="stack-pill">Mermaid.js</span>
    </div>
</footer>

{{-- ── MERMAID ─────────────────────────────────────────────────── --}}
<script src="https://cdn.jsdelivr.net/npm/mermaid@10/dist/mermaid.min.js"></script>
<script>
    mermaid.initialize({
        startOnLoad: true,
        theme: 'base',
        themeVariables: {
            background:           '#060610',
            primaryColor:         '#0f0f20',
            primaryTextColor:     '#f1f5f9',
            primaryBorderColor:   '#7c3aed',
            lineColor:            '#6366f1',
            secondaryColor:       '#0d0d1a',
            tertiaryColor:        '#0a0a14',
            clusterBkg:           '#0f0f1e',
            clusterBorder:        '#4c1d95',
            titleColor:           '#f1f5f9',
            edgeLabelBackground:  '#0d0d1a',
            nodeTextColor:        '#f1f5f9',
            fontFamily:           'Inter, system-ui, sans-serif',
            fontSize:             '13px',

            // stateDiagram specific
            stateBkg:             '#0f0f20',
            stateBorder:          '#7c3aed',
            labelColor:           '#f1f5f9',
            transitionColor:      '#6366f1',
            noteBorderColor:      '#78716c',
            noteBkgColor:         '#1c1917',
            noteTextColor:        '#d6d3d1',

            fillType0: '#3b0764',
            fillType1: '#1e3a5f',
            fillType2: '#064e3b',
            fillType3: '#451a03',
            fillType4: '#4c0519',
        },
        flowchart: {
            curve:      'basis',
            htmlLabels: true,
            padding:    20,
        },
        state: {
            padding: 10,
        },
    });
</script>

</body>
</html>
