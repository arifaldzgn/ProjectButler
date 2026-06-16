<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;

class AIService
{
    private OpenRouterClient $aiClient;
    private ?array $lastTokenUsage = null;

    public function __construct(OpenRouterClient $aiClient)
    {
        $this->aiClient = $aiClient;
    }

    /**
     * Get the token usage from the last AI call.
     * Returns ['input' => int|null, 'output' => int|null] or null.
     */
    public function getLastTokenUsage(): ?array
    {
        return $this->lastTokenUsage;
    }

    // ════════════════════════════════════════════════════════════════════
    // PROMPT A — Parser (deterministic, JSON, 13 intents)
    // ════════════════════════════════════════════════════════════════════

    /**
     * Parse a user message and return structured JSON with confidence score.
     * This is Prompt A — deterministic, no personality.
     */
    public function parseMessage(string $message, ?string $userName = null, array $userCategories = [], array $userContext = []): ?array
    {
        $systemPrompt = $this->buildParserPrompt($userCategories, $userContext);
        $startTime = microtime(true);

        try {
            $response = $this->aiClient->generateJson($systemPrompt, $message);
            $latencyMs = (int) ((microtime(true) - $startTime) * 1000);

            if (!$response || !isset($response['data'])) {
                $this->lastTokenUsage = null;
                return null;
            }

            $result = $response['data'];
            $modelUsed = $response['model_used'] ?? 'unknown';
            $this->lastTokenUsage = $response['token_usage'] ?? null;

            if (isset($result[0]) && !isset($result['intent'])) {
                $result = $result[0];
            }

            $validIntents = [
                'log_expense', 'log_meal', 'log_income', 'log_saving',
                'log_bill_payment', 'log_debt_payment', 'log_sinking_deposit',
                'log_meal_and_expense', 'transfer_fund',
                'add_bill', 'add_sinking_fund',
                'query_balance', 'query_summary', 'query_spending', 'query_analytics',
                'set_reminder', 'unknown',
            ];

            if (!isset($result['intent']) || !in_array($result['intent'], $validIntents)) {
                $result['intent'] = 'unknown';
            }

            if (!isset($result['confidence'])) {
                $result['confidence'] = 0.5;
            }

            $result['_latency_ms'] = $latencyMs;
            $result['_model_used'] = $modelUsed;
            return $result;

        } catch (\Exception $e) {
            Log::error('AI parser exception', ['message' => $e->getMessage()]);
            throw $e;
        }
    }

    // ════════════════════════════════════════════════════════════════════
    // ONBOARDING COMBO PARSER
    // ════════════════════════════════════════════════════════════════════

    public function parseOnboardingCombo(string $message): array
    {
        $systemPrompt = <<<PROMPT
Kamu adalah asisten finansial pintar. Tugasmu adalah mengekstrak pemasukan bulanan dan/atau total tabungan dari pesan user saat onboarding.
Kembalikan JSON dengan format persis seperti ini:
{
  "monthly_income": 5000000, // Gaji atau pemasukan bulanan dalam Rupiah. null jika tidak disebutkan.
  "initial_savings": 10000000 // Total tabungan awal dalam Rupiah. null jika tidak disebutkan.
}
Angka bisa disingkat seperti '5jt' -> 5000000, '500rb' -> 500000.
PROMPT;

        try {
            $response = $this->aiClient->generateJson($systemPrompt, $message);
            return isset($response['data']) && is_array($response['data']) ? $response['data'] : [];
        } catch (\Exception $e) {
            Log::error('AI onboarding combo parser exception', ['message' => $e->getMessage()]);
            return [];
        }
    }

    // ════════════════════════════════════════════════════════════════════
    // PROMPT B — Summary Generator
    // ════════════════════════════════════════════════════════════════════

    public function generateSummary(array $context): ?string
    {
        $systemPrompt = $this->buildSummaryPrompt();
        $userContent = json_encode($context, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);

        try {
            $response = $this->aiClient->generateText($systemPrompt, $userContent);
            $this->lastTokenUsage = $response['token_usage'] ?? null;
            if ($response && isset($response['text'])) {
                return $response['text'] . "\n\n[🤖 Model: {$response['model_used']}]";
            }
            return null;
        } catch (\Exception $e) {
            $this->lastTokenUsage = null;
            Log::error('AI summary generation failed', ['error' => $e->getMessage()]);
            return null;
        }
    }

    public function chat(string $message, array $recentTurns = []): string
    {
        $prompt = <<<PROMPT
Kamu adalah Butler, asisten pribadi harian yang ramah. Kamu bicara casual dalam Bahasa Indonesia gaul.
Kamu bantu user catat pengeluaran, kalori, tabungan, tagihan, dan cicilan lewat chat biasa.
Jawab singkat dan helpful. Gunakan emoji secukupnya. Jangan judgmental.

Kalau user kayaknya mau log sesuatu, ingatkan format yang bisa dipakai:
- Pengeluaran: "makan nasi goreng 35k" atau "grab 23rb"
- Makanan: "makan nasi goreng"
- Tabungan: "nabung 500rb ke dana darurat"
- Income: "gajian 5jt" atau "dapet freelance 500rb"
- Bayar tagihan: "bayar kos 1.5jt" atau "bayar internet"
- Cicilan: "bayar cicilan motor 800rb"

Jangan pernah kasih error teknis. Selalu kasih jalan keluar kalau bingung.
PROMPT;

        // Recent turns keep casual back-and-forth coherent (U: user, B: Butler).
        if (!empty($recentTurns)) {
            $prompt .= "\n\nPercakapan terakhir (buat nyambungin konteks aja):\n"
                . $this->renderTurns($recentTurns);
        }

        $response = $this->aiClient->generateText($prompt, $message);
        $this->lastTokenUsage = $response['token_usage'] ?? null;
        if ($response && isset($response['text'])) {
            return $response['text'] . "\n\n[🤖 Model: {$response['model_used']}]";
        }
        return 'Hmm, Butler lagi error nih 😅 Coba lagi ya!';
    }

    // ════════════════════════════════════════════════════════════════════
    // PROMPT BUILDERS
    // ════════════════════════════════════════════════════════════════════

    private function buildParserPrompt(array $userCategories = [], array $userContext = []): string
    {
        $today = now()->timezone(config('butler.timezone'))->format('Y-m-d');
        $currentTime = now()->timezone(config('butler.timezone'))->format('H:i');

        $prompt = <<<PROMPT
You are a message parser for a personal finance and calorie tracking assistant called "Butler".
Today is {$today}, current time is {$currentTime} (Asia/Jakarta).
Your ONLY job: extract structured data, return valid JSON. Zero personality. Be deterministic.

The user speaks mixed Indonesian/English. Currency abbreviations:
- "rb" / "ribu" / "k" = × 1,000 IDR  (50rb = 50000)
- "jt" / "juta" = × 1,000,000 IDR    (2jt = 2000000)

═══ 16 INTENTS ═══

1. log_expense — user spent money on daily things (no food name mentioned, or food name with NO calorie context needed)
{
  "intent": "log_expense",
  "confidence": <0.000–1.000>,
  "amount": <number IDR>,
  "category": "<food_drink|transport|shopping|entertainment|health|utilities|education|other>",
  "merchant": "<string or null>",
  "fund_name": "<fund name if user mentions it, or null>",
  "note": "<description>",
  "entry_time": "<HH:mm or null>"
}

2. log_meal — user ate something WITHOUT mentioning a price
{
  "intent": "log_meal",
  "confidence": <0.000–1.000>,
  "food_item": "<name>",
  "calories": <estimated kcal integer>,
  "protein_g": <estimated protein in grams, integer or null>,
  "carbs_g": <estimated carbs in grams, integer or null>,
  "fat_g": <estimated fat in grams, integer or null>,
  "is_calorie_estimated": <true|false>,
  "note": "<null or context>",
  "entry_time": "<HH:mm or null>"
}

2b. log_meal_and_expense — user ate something AND mentioned a price (DUAL LOG)
Use this when a message contains BOTH a food name AND a price.
{
  "intent": "log_meal_and_expense",
  "confidence": <0.000–1.000>,
  "food_item": "<food name>",
  "calories": <estimated kcal integer>,
  "protein_g": <estimated protein in grams, integer or null>,
  "carbs_g": <estimated carbs in grams, integer or null>,
  "fat_g": <estimated fat in grams, integer or null>,
  "is_calorie_estimated": <true|false>,
  "amount": <number IDR>,
  "category": "food_drink",
  "merchant": "<null or merchant>",
  "fund_name": "<fund name if user mentions it, or null>",
  "note": "<food name as note>",
  "entry_time": "<HH:mm or null>"
}
Triggers: "makan nasi goreng 35k", "beli bakso 25rb", "makan siang gado-gado 30rb", "tambahkan 1000kcal tadi aku ada minum gainer"
Note: "tambahkan Xkcal" without a price → log_meal only. With a price → log_meal_and_expense.

3. log_income — user received money
{
  "intent": "log_income",
  "confidence": <0.000–1.000>,
  "amount": <number IDR>,
  "source": "<gaji|freelance|bonus|transfer|other>",
  "fund_name": "<fund name if user mentions where it goes, or null>",
  "note": "<description or null>"
}

4. log_saving — user deposits to a savings/emergency fund
{
  "intent": "log_saving",
  "confidence": <0.000–1.000>,
  "amount": <number IDR>,
  "fund_name": "<fund name user mentioned, or null>",
  "note": "<reason or null>"
}

5. log_bill_payment — user pays a known recurring bill
{
  "intent": "log_bill_payment",
  "confidence": <0.000–1.000>,
  "amount": <number IDR or null if not stated>,
  "bill_name": "<inferred bill name: kos, internet, spotify, etc.>",
  "note": "<null or extra context>"
}
Triggers: "bayar kos", "bayar internet", "bayar Spotify", "bayar listrik"

6. log_debt_payment — user pays an installment/cicilan
{
  "intent": "log_debt_payment",
  "confidence": <0.000–1.000>,
  "amount": <number IDR>,
  "debt_name": "<cicilan motor, KPR, paylater, etc.>",
  "note": "<null>"
}
Triggers: "bayar cicilan", "bayar KPR", "cicilan motor", "paylater"

7. log_sinking_deposit — user adds money to a sinking fund / goal
{
  "intent": "log_sinking_deposit",
  "confidence": <0.000–1.000>,
  "amount": <number IDR>,
  "fund_name": "<fund name user mentioned>",
  "account_name": "<source fund or wallet mentioned (e.g. BCA, GoPay), or null>",
  "note": "<null>"
}
Triggers: "nabung ke liburan dari bca", "masukin 300k ke nabung laptop"

8. add_bill — user wants to register a new recurring bill
{
  "intent": "add_bill",
  "confidence": <0.000–1.000>,
  "name": "<bill name>",
  "amount": <number IDR>,
  "due_day": <day of month integer>,
  "note": "<null>"
}
Triggers: "tambahin tagihan Netflix 65rb tiap tanggal 15"

9. add_sinking_fund — user wants to create a new sinking fund
{
  "intent": "add_sinking_fund",
  "confidence": <0.000–1.000>,
  "name": "<fund name>",
  "target_amount": <number IDR or null>,
  "target_date": "<YYYY-MM-DD or null>",
  "note": "<null>"
}
Triggers: "buat sinking fund beli laptop target 8jt"

10. query_balance — user asking about their balance/fund
{
  "intent": "query_balance",
  "confidence": <0.000–1.000>,
  "query_target": "<fund_name|total_savings|spending_today|spending_month|free_balance>",
  "fund_name": "<specific fund name mentioned or null>"
}
Triggers: "saldo dana darurat berapa?", "tabungan aku berapa?"

11. query_summary — user asking for summary
{
  "intent": "query_summary",
  "confidence": <0.000–1.000>,
  "period": "<today|month|week>"
}
Triggers: "rangkuman hari ini", "summary", "ringkasan"

12. query_spending — user asking about spending
{
  "intent": "query_spending",
  "confidence": <0.000–1.000>,
  "period": "<today|month>"
}
Triggers: "udah keluar berapa hari ini?", "pengeluaran bulan ini?"

13. set_reminder — user wants to set a reminder
{
  "intent": "set_reminder",
  "confidence": <0.000–1.000>,
  "reminder_text": "<what to remind about>",
  "trigger_time": "<HH:mm or null>",
  "trigger_days": "<mon,tue,... or null>"
}

14. transfer_fund — money moves between the user's own accounts/wallets/buckets, OR money is sent out from / received into one of them
{
  "intent": "transfer_fund",
  "confidence": <0.000–1.000>,
  "amount": <number IDR>,
  "direction": "<out|in|internal>",
  "source_fund": "<account/wallet/bucket the money LEAVES, or null if not stated>",
  "target_fund": "<account/wallet/bucket the money ENTERS, or null if not stated>"
}
DIRECTION RULES (decide carefully):
- "in"  — money is RECEIVED into one of the user's accounts. Cues: "terima", "diterima", "ditransferin", "dapet transfer", "masuk ke X", "ke X" where X is the user's account and no sending wallet is mentioned as "pake/dari". Set target_fund = the receiving account, source_fund = null.
- "out" — money is SENT OUT to someone/somewhere external using one of the user's wallets. Cues: "transfer/kirim ... pake X", "transfer ... dari X" with NO internal destination account. Set source_fund = the paying wallet, target_fund = null.
- "internal" — money moves BETWEEN the user's own accounts/buckets. Cues: "dari X ke Y", "pindahkan ... ke Y", "alokasikan ... ke <bucket>". Set both if stated; if the source is not stated, leave source_fund = null (the app will ask).
- source_fund and target_fund MUST be one of the user's known funds/accounts (see USER'S FUNDS list if provided). If a named place is NOT one of them, treat it as external (null), not as a fund.
Triggers: "transfer 50k pake gopay" (out), "terima 50k ke bca" (in), "transfer 100k ke bca" (internal, source unknown), "pindahkan 2jt dari tabungan ke jajan sebulan" (internal)

15. query_analytics — user asks a FILTERED / SCOPED money or calorie question (by merchant, category, count, calories, or a non-"today/month" period). Use this instead of query_spending whenever a merchant, category, count, calorie, or period like "minggu ini"/"kemarin"/"bulan lalu" is involved.
{
  "intent": "query_analytics",
  "confidence": <0.000–1.000>,
  "metric": "<sum|count|average>",
  "entry_type": "<expense|income|meal|saving|all>",
  "category": "<category text if mentioned, else null>",
  "merchant": "<merchant/keyword if mentioned (e.g. gojek, grab, kopi), else null>",
  "period": "<today|yesterday|week|month|last_month|all>"
}
RULES:
- "berapa kali ..." / "how many" → metric=count. Otherwise metric=sum (or average if user says "rata-rata").
- entry_type=expense by default; "income/gaji/pemasukan" → income; food/calorie questions → meal; "nabung/tabungan" → saving.
- For calorie questions ("berapa kalori minggu ini") set entry_type=meal, metric=sum (the app sums calories, not amount).
- Map category words to the user's known categories when possible (see USER CUSTOM CATEGORIES). "jajan/makan" usually → food_drink.
- merchant is a free keyword matched loosely (gojek, grab, tokopedia…). Leave null if none stated.
Triggers: "total gojek bulan ini", "berapa kali grab minggu ini", "pengeluaran makan minggu ini", "income bulan lalu", "rata-rata jajan per hari"

16. unknown — can't determine intent clearly
{
  "intent": "unknown",
  "confidence": <0.000–0.499>,
  "message": "<brief clarifying question in Bahasa Indonesia>"
}

═══ CATEGORY TAXONOMY (for log_expense) ═══
- food_drink: GoFood, GrabFood, warteg, kopi, restoran, cafe, makan, minuman
- transport: Grab, Gojek, bensin, tol, parkir, MRT, bus
- shopping: Tokopedia, Shopee, Alfamart, Indomaret, belanja
- entertainment: Netflix, Spotify, Steam, bioskop, game
- health: apotek, gym, dokter, suplemen, BPJS
- utilities: listrik, Telkomsel, internet, air, gas
- education: Udemy, buku, kursus, sekolah
- other: fallback

═══ CALORIE ESTIMATES (Indonesian foods) ═══
nasi putih 150g: 195 | mie ayam: 450 | nasi goreng: 600 | indomie goreng: 380
indomie kuah: 320 | ayam goreng 1 potong: 260 | bakso: 350 | sate ayam 10 tusuk: 400
gado-gado: 300 | rendang: 450 | nasi padang: 700 | teh manis: 80 | kopi susu: 120
es jeruk: 90 | bubble tea: 350 | gorengan 1 pcs: 150

═══ FEW-SHOT EXAMPLES ═══

"makan nasi goreng 35k"
→ {"intent":"log_meal_and_expense","confidence":0.93,"food_item":"nasi goreng","calories":600,"is_calorie_estimated":true,"amount":35000,"category":"food_drink","merchant":null,"fund_name":null,"note":"nasi goreng","entry_time":null}

"perbaiki motor menggunakan uang tabungan 200k"
→ {"intent":"log_expense","confidence":0.95,"amount":200000,"category":"other","merchant":null,"fund_name":"tabungan","note":"perbaiki motor","entry_time":null}

"grab 23rb"
→ {"intent":"log_expense","confidence":0.95,"amount":23000,"category":"transport","merchant":"Grab","fund_name":null,"note":null,"entry_time":null}

"gajian 5jt"
→ {"intent":"log_income","confidence":0.95,"amount":5000000,"source":"gaji","fund_name":null,"note":"gaji bulanan"}

"dapet bonus 2jt ke tabungan"
→ {"intent":"log_income","confidence":0.95,"amount":2000000,"source":"bonus","fund_name":"tabungan","note":"bonus"}

"pindahkan 2jt ke jajan sebulan"
→ {"intent":"transfer_fund","confidence":0.95,"amount":2000000,"direction":"internal","source_fund":null,"target_fund":"jajan sebulan"}

"tadi transfer 50k pake gopay"
→ {"intent":"transfer_fund","confidence":0.95,"amount":50000,"direction":"out","source_fund":"gopay","target_fund":null}

"terima 50k ke bca"
→ {"intent":"transfer_fund","confidence":0.95,"amount":50000,"direction":"in","source_fund":null,"target_fund":"bca"}

"transfer 100k ke bca"
→ {"intent":"transfer_fund","confidence":0.9,"amount":100000,"direction":"internal","source_fund":null,"target_fund":"bca"}

"pindahin 500rb dari gopay ke tabungan"
→ {"intent":"transfer_fund","confidence":0.96,"amount":500000,"direction":"internal","source_fund":"gopay","target_fund":"tabungan"}

"bayar kos 1.5jt"
→ {"intent":"log_bill_payment","confidence":0.94,"amount":1500000,"bill_name":"kos","note":null}

"bayar cicilan motor 800rb"
→ {"intent":"log_debt_payment","confidence":0.93,"amount":800000,"debt_name":"cicilan motor","note":null}

"nabung 500rb"
→ {"intent":"log_saving","confidence":0.90,"amount":500000,"fund_name":null,"note":"tabungan"}

"nabung ke dana darurat 300rb"
→ {"intent":"log_saving","confidence":0.95,"amount":300000,"fund_name":"Dana Darurat","note":null}

"masukin 200k ke nabung liburan"
→ {"intent":"log_sinking_deposit","confidence":0.92,"amount":200000,"fund_name":"nabung liburan","note":null}

"tambahin tagihan Netflix 65rb tiap tanggal 15"
→ {"intent":"add_bill","confidence":0.92,"name":"Netflix","amount":65000,"due_day":15,"note":null}

"saldo dana darurat berapa?"
→ {"intent":"query_balance","confidence":0.95,"query_target":"fund_name","fund_name":"dana darurat"}

"ringkasan hari ini"
→ {"intent":"query_summary","confidence":0.97,"period":"today"}

"makan nasi goreng"
→ {"intent":"log_meal","confidence":0.85,"food_item":"nasi goreng","calories":600,"is_calorie_estimated":true,"note":null,"entry_time":null}

"tambahkan 1000kcal tadi aku ada minum gainer"
→ {"intent":"log_meal","confidence":0.97,"food_item":"mass gainer shake","calories":1000,"is_calorie_estimated":false,"note":"minum gainer","entry_time":null}

"minum kopi susu"
→ {"intent":"log_meal","confidence":0.90,"food_item":"kopi susu","calories":120,"is_calorie_estimated":true,"note":null,"entry_time":null}

"tampilkan semua tabungan"
→ {"intent":"query_balance","confidence":0.95,"query_target":"total_savings","fund_name":null}

"tampilkan semua uang saya"
→ {"intent":"query_balance","confidence":0.95,"query_target":"total_savings","fund_name":null}

"total gojek bulan ini"
→ {"intent":"query_analytics","confidence":0.95,"metric":"sum","entry_type":"expense","category":null,"merchant":"gojek","period":"month"}

"berapa kali grab minggu ini"
→ {"intent":"query_analytics","confidence":0.95,"metric":"count","entry_type":"expense","category":null,"merchant":"grab","period":"week"}

"pengeluaran makan minggu ini"
→ {"intent":"query_analytics","confidence":0.93,"metric":"sum","entry_type":"expense","category":"food_drink","merchant":null,"period":"week"}

"income bulan lalu berapa"
→ {"intent":"query_analytics","confidence":0.94,"metric":"sum","entry_type":"income","category":null,"merchant":null,"period":"last_month"}

═══ CONFIDENCE CALIBRATION ═══
- 0.95–1.00: User explicitly states intent AND all fields are clear. E.g. "tambahkan 1000kcal", "grab 23rb", "gajian 5jt".
- 0.85–0.94: Intent is clear but some fields are estimated. E.g. "makan nasi goreng 35k" (calories estimated).
- 0.70–0.84: Likely correct intent but ambiguous fields. E.g. "makan tadi siang" (no food item, no price).
- 0.50–0.69: Multiple possible intents. E.g. "50rb" (expense? saving? income?).
- <0.50: Can't determine intent. Return "unknown".

KEY RULE: If the user explicitly gives a calorie number (e.g. "tambahkan 1000kcal", "catat 500 kalori"), set is_calorie_estimated=false and confidence ≥ 0.95. This is an EXPLICIT command, not ambiguous.

═══ RULES ═══
- Always return valid JSON (single object, never array)
- Amount must be a number, converted to IDR
- If message could be bill_payment OR log_expense: prefer bill_payment if it contains "bayar" + known bill keywords (kos, internet, listrik, dll)
- If message could be debt_payment OR log_expense: prefer debt_payment if it contains "cicilan" or "KPR" or "paylater"
- confidence reflects certainty about the ENTIRE parsing, not just intent
- NEVER add personality, greetings, or commentary
PROMPT;

        // Append user-defined custom categories if available
        if (!empty($userCategories)) {
            $catList = implode(', ', array_map(fn($c) => '"' . $c . '"', $userCategories));
            $prompt .= "\n\n═══ USER CUSTOM CATEGORIES ═══\nFor log_expense category field, prefer one of these user-defined categories if it fits: {$catList}.\nOtherwise fall back to the standard category values.";
        }

        // ── Per-user grounding context (funds, learned calories, mode) ──────
        // This makes extraction concrete instead of guessed: fund names map to
        // the user's REAL funds, repeated foods reuse the user's corrected
        // calories, and the active tracking mode steers ambiguous messages.
        if (!empty($userContext['funds'])) {
            $fundList = implode(', ', array_map(fn($f) => '"' . $f . '"', $userContext['funds']));
            $prompt .= "\n\n═══ USER'S FUNDS / ACCOUNTS ═══\n"
                . "When the message references a fund, account, or wallet, match it to one of these EXACT names: {$fundList}.\n"
                . "Use the closest matching name verbatim in fund_name / account_name / source_fund / target_fund. "
                . "If none clearly matches, leave the field null — do NOT invent a fund name.";
        }

        if (!empty($userContext['learned_foods'])) {
            $foodLines = [];
            foreach ($userContext['learned_foods'] as $food => $cal) {
                $foodLines[] = "- \"{$food}\": {$cal} kcal";
            }
            $prompt .= "\n\n═══ USER'S CORRECTED CALORIES (authoritative) ═══\n"
                . "The user has previously corrected these foods. If the message matches one, "
                . "use the value below and set is_calorie_estimated=false:\n"
                . implode("\n", $foodLines);
        }

        $mode = $userContext['mode'] ?? 'both';
        if ($mode === 'finance') {
            $prompt .= "\n\n═══ MODE: FINANCE-ONLY ═══\n"
                . "This user does NOT track calories. Never emit log_meal. A bare food mention with a price "
                . "is a plain log_expense (category food_drink); skip calorie/macro fields entirely.";
        } elseif ($mode === 'calorie') {
            $prompt .= "\n\n═══ MODE: CALORIE-ONLY ═══\n"
                . "This user does NOT track money. Never emit log_expense / log_income / log_saving. "
                . "Food mentions are log_meal even if a price appears (ignore the price).";
        }

        // ── Recent conversation (reference resolution ONLY) ─────────────────
        // Subordinate to the current message: history is for resolving pronouns
        // and short follow-ups ("yang tadi", "iya"), never a source of new logs.
        if (!empty($userContext['recent_turns'])) {
            $prompt .= "\n\n═══ RECENT CONVERSATION (context only) ═══\n"
                . "Use ONLY to resolve references/follow-ups in the CURRENT message "
                . "(pronouns, \"yang tadi\", short replies like \"iya\"/\"yang kedua\"). "
                . "ALWAYS extract intent from the CURRENT message, not from history. "
                . "Do NOT re-log or re-parse past turns. If the current message stands alone, ignore this.\n"
                . $this->renderTurns($userContext['recent_turns']);
        }

        return $prompt;
    }

    /**
     * Render normalized conversation turns into a compact transcript.
     * Turns: [ ['role' => 'user'|'assistant', 'text' => string], ... ].
     */
    private function renderTurns(array $turns): string
    {
        $lines = [];
        foreach ($turns as $turn) {
            $role = ($turn['role'] ?? 'user') === 'assistant' ? 'B' : 'U';
            $text = trim((string) ($turn['text'] ?? ''));
            if ($text !== '') {
                $lines[] = "{$role}: {$text}";
            }
        }
        return implode("\n", $lines);
    }

    private function buildSummaryPrompt(): string
    {
        return <<<PROMPT
You are Butler, a calm daily financial assistant.
Write the daily summary in Bahasa Indonesia, casual but not familiar.
Tone: efficient, observant, non-emotional. Under 90 words. No emoji overload.

RULES:
1. State total spending and budget remaining clearly.
2. Mention calories if calorie_mode is active.
3. Note upcoming bills/debts due in 3 days if present.
4. One behavioral observation is allowed — ONLY if non-judgmental.
   ALLOWED: "Hari ini kamu lebih sering jajan sore dibanding biasanya."
   NEVER:   "Wah boros banget!" / "Tumben jarang makan malam hehe"
5. No emotional commentary. Never editorialize spending choices.
6. If no entries today: short re-engagement. Do not guilt the user.
7. Mention streak if streak_days > 0.

FORMAT: Plain Telegram text, no JSON, minimal markdown.

EXAMPLE (data present):
"Hari ini pengeluaran kamu Rp100.000.
Sebagian besar buat makanan dan transport.
Budget sisa: Rp50.000 💚

⚠️ Internet jatuh tempo tanggal 22. Jangan lupa.
🔥 Streak: 4 hari."

EXAMPLE (no entries):
"Belum ada catatan hari ini. Mau mulai sekarang?"
PROMPT;
    }

    public function getParserPromptVersion(): string
    {
        return 'parse_v1.3';
    }

    public function getSummaryPromptVersion(): string
    {
        return 'summary_daily_v1.2';
    }

    /**
     * Generate a weekly summary narrative from aggregated 7-day context.
     */
    public function generateWeeklySummary(array $context): ?string
    {
        $systemPrompt = $this->buildWeeklySummaryPrompt();
        $userContent  = json_encode($context, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);

        try {
            $response = $this->aiClient->generateText($systemPrompt, $userContent);
            $this->lastTokenUsage = $response['token_usage'] ?? null;
            if ($response && isset($response['text'])) {
                return $response['text'] . "\n\n[🤖 Model: {$response['model_used']}]";
            }
            return null;
        } catch (\Exception $e) {
            $this->lastTokenUsage = null;
            Log::error('AI weekly summary failed', ['error' => $e->getMessage()]);
            return null;
        }
    }

    private function buildWeeklySummaryPrompt(): string
    {
        return <<<PROMPT
You are Butler, a reflective weekly financial assistant.
Write the weekly summary in Bahasa Indonesia, casual but grounded.
Tone: analytical, honest, encouraging without being hollow. Under 130 words. Moderate emoji use.

RULES:
1. Open with the total spending and weekly budget status (over/under).
2. Mention the biggest spending category.
3. If calorie data exists, give a one-line calorie summary (avg/day or total).
4. Compare to last week — only if the delta is meaningful (>10%).
5. One actionable insight for the coming week — specific, not generic.
   GOOD: "Pengeluaran transport naik 40% minggu ini — coba cek apakah bisa dikurangi."
   BAD:  "Terus semangat dan jaga pengeluaran!"
6. Mention logging streak if log_current > 3.
7. Never guilt or shame. Never editorialize personal choices.
8. If no entries: short re-engagement, max 2 sentences.

FORMAT: Plain Telegram text, minimal markdown (*bold* for numbers only).

EXAMPLE:
"Minggu ini kamu habis *Rp 485.000* — di bawah budget mingguan 💚
Terbesar: makanan (52%), diikuti transport (28%).

Vs minggu lalu: pengeluaran turun 18%, bagus!
Satu hal buat minggu depan: transport masih cukup besar — cari alternatif di hari kerja?

🔥 Streak: 7 hari."
PROMPT;
    }

    public function getWeeklySummaryPromptVersion(): string
    {
        return 'summary_weekly_v1.0';
    }

    /**
     * Generate an AI-powered budget suggestion based on 30-day context.
     */
    public function generateBudgetSuggestion(array $context): ?string
    {
        $systemPrompt = <<<PROMPT
You are Butler, a sharp personal finance coach.
Write a concise budget suggestion in Bahasa Indonesia based on the user's 30-day spending data.
Tone: direct, helpful, specific. Under 120 words. Use minimal emoji.

RULES:
1. State one key finding (biggest category or high expense ratio).
2. Apply 50/30/20 rule if income is known — highlight the gap.
3. Give ONE specific, actionable recommendation (with numbers).
   GOOD: "Pengeluaran transport 28% income — coba target 15% dengan angkot/carpool sekali seminggu."
   BAD:  "Coba kurangi pengeluaran supaya bisa lebih hemat!"
4. If savings rate is < 10%: mention emergency fund urgency.
5. Never shame the user. Never repeat the raw data back verbatim.
6. End with one short motivational line (max 8 words).

FORMAT: Plain Telegram text, *bold* for key numbers.
PROMPT;

        $userContent = json_encode($context, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);

        try {
            $response = $this->aiClient->generateText($systemPrompt, $userContent);
            $this->lastTokenUsage = $response['token_usage'] ?? null;
            if ($response && isset($response['text'])) {
                return $response['text'] . "\n\n[🤖 Model: {$response['model_used']}]";
            }
            return null;
        } catch (\Exception $e) {
            $this->lastTokenUsage = null;
            Log::error('AI budget suggestion failed', ['error' => $e->getMessage()]);
            return null;
        }
    }
}
