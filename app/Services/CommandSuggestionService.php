<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;

/**
 * CommandSuggestionService — "Did you mean…?" / "Try this instead" engine.
 *
 * Used as a fallback layer when MessageRouter fails to confidently parse a
 * Telegram message (parse exception, confidence < 0.50, or intent='unknown').
 *
 * Two-stage hybrid:
 *   Stage 1 — Deterministic keyword + fuzzy match (fast, free, < 5ms).
 *   Stage 2 — One AI call only if Stage 1 misses (smarter, paid on failure only).
 *
 * Returns null when nothing matches — caller should fall back to the existing
 * canned "bingung" reply so we never block on this.
 */
class CommandSuggestionService
{
    private OpenRouterClient $ai;

    // Stage-1 threshold — read from config so admin can tune without a deploy.
    // Set BUTLER_SUGGESTION_THRESHOLD in .env (default 0.55).
    private float $similarityThreshold;

    public function __construct(OpenRouterClient $ai)
    {
        $this->ai = $ai;
        $this->similarityThreshold = (float) config('butler.suggestion_threshold', 0.55);
    }

    /**
     * Catalog of supported capabilities. Each row is what Stage-1 matches
     * against and what we cite in user-facing suggestions.
     *
     * Order matters only for ties — first row wins.
     */
    private function catalog(): array
    {
        return [
            // ── Logging entries ────────────────────────────────────────────
            ['slug' => 'log_expense', 'label' => 'Catat Pengeluaran',
             'triggers' => ['expense','spent','pengeluaran','bayar','beli','grab','gofood','jajan','keluar duit'],
             'example' => '50k makan siang'],
            ['slug' => 'log_meal', 'label' => 'Catat Makanan',
             'triggers' => ['makan','meal','breakfast','lunch','dinner','sarapan','makanan','kuliner','lapar'],
             'example' => 'makan nasi goreng'],
            ['slug' => 'log_income', 'label' => 'Catat Pemasukan',
             'triggers' => ['income','gaji','salary','freelance','bonus','dapet','dapat duit','transfer masuk','terima'],
             'example' => 'gaji 5jt'],
            ['slug' => 'log_saving', 'label' => 'Catat Tabungan',
             'triggers' => ['nabung','saving','tabung','simpan','tabungan','setor'],
             'example' => 'nabung 500rb'],
            ['slug' => 'log_bill_payment', 'label' => 'Bayar Tagihan',
             'triggers' => ['bayar tagihan','bayar kos','bayar internet','bayar listrik','bayar spotify','tagihan'],
             'example' => 'bayar kos 2jt'],
            ['slug' => 'log_debt_payment', 'label' => 'Bayar Cicilan',
             'triggers' => ['cicilan','installment','kredit','kpr','paylater','bayar motor','bayar cicilan'],
             'example' => 'bayar cicilan motor 1.5jt'],
            ['slug' => 'log_sinking_deposit', 'label' => 'Setor ke Sinking Fund',
             'triggers' => ['setor fund','isi sinking','kumpulin','isi dana','top up fund'],
             'example' => 'setor 500rb ke dana liburan'],

            // ── Manage bills / funds ───────────────────────────────────────
            ['slug' => 'add_bill', 'label' => 'Tambah Tagihan Rutin',
             'triggers' => ['tambah tagihan','daftar tagihan baru','tagihan baru','add bill','catat tagihan rutin'],
             'example' => 'tambah tagihan netflix 200rb tiap tgl 5'],
            ['slug' => 'add_sinking_fund', 'label' => 'Buat Goal / Sinking Fund',
             'triggers' => ['bikin fund','buat tabungan','goal baru','target nabung','sinking fund'],
             'example' => 'bikin goal liburan bali 5jt'],
            ['slug' => 'transfer_fund', 'label' => 'Pindah Dana Antar Akun',
             'triggers' => ['transfer','pindah','move','transfer ke','pindahin'],
             'example' => 'pindah 1jt dari kas ke tabungan'],

            // ── Queries ────────────────────────────────────────────────────
            ['slug' => 'query_balance', 'label' => 'Cek Saldo',
             'triggers' => ['saldo','balance','cek saldo','lihat saldo','dompet','sisa duit','duit tinggal','cek dompet'],
             'example' => 'saldo'],
            ['slug' => 'query_summary', 'label' => 'Lihat Ringkasan Hari',
             'triggers' => ['summary','ringkasan','recap','rangkuman','hari ini','today'],
             'example' => 'ringkasan'],
            ['slug' => 'query_spending', 'label' => 'Lihat Pengeluaran',
             'triggers' => ['pengeluaran bulan','spending','total spent','habis berapa','udah keluar berapa'],
             'example' => 'spending bulan ini'],
            ['slug' => 'set_reminder', 'label' => 'Buat Pengingat',
             'triggers' => ['ingetin','reminder','reminder ke','tolong inget','jangan lupa','remind'],
             'example' => 'ingetin gw bayar listrik tiap tgl 5'],

            // ── Quick commands (non-AI shortcuts) ──────────────────────────
            ['slug' => 'list_bills', 'label' => 'Daftar Tagihan',
             'triggers' => ['list tagihan','daftar tagihan','tagihan apa aja','bills'],
             'example' => 'tagihan'],
            ['slug' => 'open_dashboard', 'label' => 'Buka Dashboard',
             'triggers' => ['dashboard','buka dashboard','web','webview','lihat dashboard'],
             'example' => 'buka dashboard'],
            ['slug' => 'open_settings', 'label' => 'Buka Pengaturan',
             'triggers' => ['settings','pengaturan','setelan','ubah profil','ganti pengaturan'],
             'example' => 'settings'],
            ['slug' => 'log_mood', 'label' => 'Catat Mood',
             'triggers' => ['mood','perasaan','mood gw','feeling','lagi sedih','lagi happy'],
             'example' => 'mood: good, energi 4'],
            ['slug' => 'help', 'label' => 'Bantuan',
             'triggers' => ['help','bantuan','gimana cara','tutorial','cara pakai'],
             'example' => 'help'],
        ];
    }

    /**
     * Main entry point. Returns suggestion array or null.
     *
     * Shape:
     *   ['type' => 'did_you_mean'|'unsupported',
     *    'slug' => string,
     *    'label' => string,
     *    'example' => string,
     *    'reply' => string,       // formatted Telegram-ready text
     *    'stage' => 1|2,          // which stage produced it (for logging)
     *    'score' => float|null]   // Stage-1 only
     */
    public function suggest(string $input): ?array
    {
        $normalized = $this->normalize($input);
        if ($normalized === '') return null;

        // ── Stage 1: deterministic keyword + fuzzy ─────────────────────────
        $stage1 = $this->deterministicMatch($normalized);
        if ($stage1) {
            return $this->formatReply($stage1, 'did_you_mean', 1);
        }

        // ── Stage 2: AI fallback ───────────────────────────────────────────
        $stage2 = $this->aiMatch($input);
        if ($stage2) {
            return $this->formatReply($stage2['catalog'], $stage2['kind'], 2, $stage2['user_message'] ?? null);
        }

        return null;
    }

    // ── Stage 1 ────────────────────────────────────────────────────────────

    private function deterministicMatch(string $normalized): ?array
    {
        $inputTokens = preg_split('/\s+/', $normalized, -1, PREG_SPLIT_NO_EMPTY);
        if (empty($inputTokens)) return null;

        $best       = null;
        $bestScore  = 0.0;

        foreach ($this->catalog() as $entry) {
            $maxScore = 0.0;
            foreach ($entry['triggers'] as $trigger) {
                $triggerNorm = $this->normalize($trigger);
                $score = $this->similarity($normalized, $triggerNorm, $inputTokens);
                if ($score > $maxScore) $maxScore = $score;
            }
            if ($maxScore > $bestScore) {
                $bestScore = $maxScore;
                $best      = $entry;
                $best['score'] = $maxScore;
            }
        }

        return ($best && $bestScore >= $this->similarityThreshold) ? $best : null;
    }

    /**
     * Weighted similarity: 0.6 × best-token-levenshtein + 0.4 × jaccard overlap.
     * Substring contains gives a bonus.
     */
    private function similarity(string $input, string $trigger, array $inputTokens): float
    {
        // Substring contains is a strong signal (e.g. "saldoo" ⊃ "saldo")
        if (str_contains($input, $trigger) || str_contains($trigger, $input)) {
            // Penalize only mildly when the lengths differ a lot
            $lenRatio = min(strlen($input), strlen($trigger)) / max(strlen($input), strlen($trigger));
            return 0.65 + 0.35 * $lenRatio;
        }

        // Token-level Jaccard
        $triggerTokens = preg_split('/\s+/', $trigger, -1, PREG_SPLIT_NO_EMPTY);
        $jaccard = $this->jaccard($inputTokens, $triggerTokens);

        // Best single-token Levenshtein (handles typos like saldoo → saldo)
        $bestLev = 0.0;
        foreach ($inputTokens as $it) {
            foreach ($triggerTokens as $tt) {
                $maxLen = max(strlen($it), strlen($tt));
                if ($maxLen === 0) continue;
                $lev = levenshtein($it, $tt);
                $score = 1.0 - ($lev / $maxLen);
                if ($score > $bestLev) $bestLev = $score;
            }
        }

        return 0.6 * $bestLev + 0.4 * $jaccard;
    }

    private function jaccard(array $a, array $b): float
    {
        if (empty($a) || empty($b)) return 0.0;
        $aSet = array_unique($a);
        $bSet = array_unique($b);
        $intersect = count(array_intersect($aSet, $bSet));
        $union     = count(array_unique(array_merge($aSet, $bSet)));
        return $union > 0 ? $intersect / $union : 0.0;
    }

    private function normalize(string $s): string
    {
        $s = mb_strtolower(trim($s));
        $s = preg_replace('/[^\p{L}\p{N}\s]+/u', ' ', $s);
        $s = preg_replace('/\s+/', ' ', $s);
        return trim($s);
    }

    // ── Stage 2 ────────────────────────────────────────────────────────────

    private function aiMatch(string $input): ?array
    {
        $catalog = $this->catalog();
        $list = collect($catalog)->map(fn($c) =>
            "- {$c['slug']}: {$c['label']} (ex: \"{$c['example']}\")"
        )->implode("\n");

        $systemPrompt = <<<PROMPT
You are a routing helper for a personal-finance Telegram bot called Butler.
The bot has FIXED supported commands below. The user just sent a message that
the primary parser could NOT classify. Decide if the user *probably* meant one
of these commands (typo / paraphrase) or if they're asking for something we
do NOT support yet.

Supported commands:
$list

Respond with STRICT JSON only. Schema:
{
  "match": "<slug>" | null,
  "kind": "did_you_mean" | "unsupported" | "unclear",
  "user_message": "<one-line Indonesian-language explanation, max 120 chars>"
}

Rules:
- "did_you_mean": user clearly intended an existing command but mis-phrased.
- "unsupported": user clearly wants a feature we don't have (e.g. predictions, multi-user accounts, OCR).
- "unclear": you genuinely can't tell — message is too vague or random.
- If "unsupported"/"unclear", pick the *closest* supported slug in "match" anyway (or null if none fit at all).
PROMPT;

        try {
            $resp = $this->ai->generateJson($systemPrompt, $input);
            if (!$resp || empty($resp['data'])) return null;

            $data = $resp['data'];
            $kind = $data['kind'] ?? null;
            $slug = $data['match'] ?? null;

            if (!in_array($kind, ['did_you_mean', 'unsupported', 'unclear'])) return null;
            if ($kind === 'unclear' && !$slug) return null;

            // Resolve catalog entry for slug
            $matched = collect($catalog)->firstWhere('slug', $slug);
            if (!$matched && $kind !== 'unclear') return null;

            return [
                'catalog'     => $matched,
                'kind'        => $kind,
                'user_message'=> $data['user_message'] ?? null,
            ];
        } catch (\Throwable $e) {
            Log::warning('CommandSuggestionService stage-2 AI fallback failed', [
                'input' => $input,
                'error' => $e->getMessage(),
            ]);
            return null;
        }
    }

    // ── Reply formatting ───────────────────────────────────────────────────

    private function formatReply(array $entry, string $kind, int $stage, ?string $aiMessage = null): array
    {
        $label   = $entry['label'];
        $example = $entry['example'];

        $reply = match ($kind) {
            'did_you_mean' => "🤔 Maksudmu: *{$label}*?\nCoba kirim:\n`{$example}`",
            'unsupported'  => "Butler belum bisa itu 😅\n"
                              . ($aiMessage ? "_{$aiMessage}_\n\n" : "")
                              . "Tapi mungkin kamu bisa coba *{$label}*:\n`{$example}`",
            'unclear'      => "🤔 Butler kurang yakin. Mungkin maksudmu *{$label}*?\nCoba: `{$example}`",
            default        => "Coba kirim: `{$example}`",
        };

        return [
            'type'    => $kind === 'unsupported' ? 'unsupported' : 'did_you_mean',
            'kind'    => $kind,
            'slug'    => $entry['slug'],
            'label'   => $label,
            'example' => $example,
            'reply'   => $reply,
            'stage'   => $stage,
            'score'   => $entry['score'] ?? null,
        ];
    }
}
