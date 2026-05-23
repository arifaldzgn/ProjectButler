<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;

class AIService
{
    private GeminiClient $gemini;

    public function __construct(GeminiClient $gemini)
    {
        $this->gemini = $gemini;
    }

    // ════════════════════════════════════════════════════════════════════
    // PROMPT A — Parser (deterministic, JSON, no personality)
    // ════════════════════════════════════════════════════════════════════

    /**
     * Parse a user message and extract intent + structured data.
     *
     * Returns parsed JSON with confidence score, or null on failure.
     * This is Prompt A — deterministic, returns JSON, no personality.
     */
    public function parseMessage(string $message, ?string $userName = null): ?array
    {
        $systemPrompt = $this->buildParserPrompt();
        $startTime = microtime(true);

        try {
            $result = $this->gemini->generateJson($systemPrompt, $message);
            $latencyMs = (int) ((microtime(true) - $startTime) * 1000);

            if (!$result) {
                Log::warning('AI parser returned empty result', ['message' => $message]);
                return null;
            }

            // Normalize: ensure we have a single intent object (not array of intents)
            if (isset($result[0]) && !isset($result['intent'])) {
                $result = $result[0];
            }

            // Validate required fields
            $validIntents = ['expense', 'meal', 'saving', 'query', 'general'];
            if (!isset($result['intent']) || !in_array($result['intent'], $validIntents)) {
                $result['intent'] = 'general';
            }

            // Ensure confidence score exists
            if (!isset($result['confidence'])) {
                $result['confidence'] = 0.5;
            }

            $result['_latency_ms'] = $latencyMs;

            return $result;

        } catch (\Exception $e) {
            Log::error('AI parser exception', ['message' => $e->getMessage()]);
            throw $e;
        }
    }

    // ════════════════════════════════════════════════════════════════════
    // PROMPT B — Summary Generator (generative, personality, natural text)
    // ════════════════════════════════════════════════════════════════════

    /**
     * Generate a daily summary using AI.
     *
     * This is Prompt B — generative, returns natural language, all personality.
     * Takes structured context and returns a casual Bahasa Indonesia summary.
     */
    public function generateSummary(array $context): ?string
    {
        $systemPrompt = $this->buildSummaryPrompt();
        $userContent = json_encode($context, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);

        try {
            $response = $this->gemini->generateText($systemPrompt, $userContent);
            return $response;
        } catch (\Exception $e) {
            Log::error('AI summary generation failed', ['error' => $e->getMessage()]);
            return null;
        }
    }

    /**
     * Generate a conversational response for general messages.
     */
    public function chat(string $message): string
    {
        $prompt = <<<PROMPT
Kamu adalah Butler, asisten pribadi harian yang ramah. Kamu bicara casual dalam Bahasa Indonesia gaul.
Kamu bantu user catat pengeluaran, kalori, dan tabungan lewat chat biasa.
Jawab singkat dan helpful. Gunakan emoji secukupnya. Jangan judgmental.

Kalau user kayaknya mau log sesuatu, ingatkan format yang bisa dipakai:
- Pengeluaran: "makan nasi goreng 35k" atau "grab 23rb"
- Makanan: "makan nasi goreng" atau "lunch mie ayam"
- Tabungan: "nabung 500rb" atau "save 1jt buat emergency"

Jangan pernah kasih error teknis. Selalu kasih jalan keluar kalau bingung.
PROMPT;

        $response = $this->gemini->generateText($prompt, $message);
        return $response ?? 'Hmm, Butler lagi error nih 😅 Coba lagi ya!';
    }

    // ════════════════════════════════════════════════════════════════════
    // PROMPT BUILDERS
    // ════════════════════════════════════════════════════════════════════

    /**
     * Build Prompt A — the parser system prompt.
     * Deterministic, returns JSON, no personality.
     */
    private function buildParserPrompt(): string
    {
        $today = now()->timezone(config('butler.timezone'))->format('Y-m-d');
        $currentTime = now()->timezone(config('butler.timezone'))->format('H:i');

        return <<<PROMPT
You are a message parser for a personal finance and calorie tracking assistant.
Today's date is {$today}, current time is {$currentTime} (Asia/Jakarta timezone).
Your ONLY job is to extract structured data from the user's message and return JSON.
Do NOT add personality, greetings, or commentary. Be deterministic.

The user speaks mixed Indonesian (Bahasa) and English. Common abbreviations:
- "rb" or "ribu" = thousand (ribu) in IDR, e.g. "50rb" = 50000
- "jt" or "juta" = million in IDR, e.g. "2jt" = 2000000
- "k" = thousand, e.g. "50k" = 50000
- "cek" / "check" = query intent
- "brp" / "berapa" = how much (query)
- "nabung" / "save" / "tabung" / "sisihkan" = saving intent

INSTRUCTIONS:
Return a single JSON object. The "intent" and "confidence" fields are REQUIRED.
"confidence" is a number between 0.000 and 1.000 representing how sure you are.

═══ INTENT TYPES ═══

1. intent: "expense" (user spent money)
{
  "intent": "expense",
  "confidence": <0.000-1.000>,
  "amount": <number in IDR>,
  "category": "<food_drink|transport|shopping|entertainment|health|utilities|education|other>",
  "merchant": "<merchant name or null>",
  "note": "<description in original language>",
  "entry_time": "<HH:mm or null if not specified>"
}

Category taxonomy:
- food_drink: GoFood, GrabFood, warteg, kopi, restoran, cafe
- transport: Grab, Gojek, bensin, tol, parkir, MRT
- shopping: Tokopedia, Shopee, Alfamart, Indomaret
- entertainment: Netflix, Steam, bioskop, game
- health: apotek, gym, dokter, suplemen
- utilities: listrik, Telkomsel, internet, air, gas
- education: Udemy, buku, kursus, sekolah
- other: fallback for anything else

2. intent: "meal" (user ate something)
{
  "intent": "meal",
  "confidence": <0.000-1.000>,
  "food_item": "<name of food in original language>",
  "calories": <estimated kcal as integer>,
  "is_calorie_estimated": <true if AI estimated, false if from known source>,
  "note": "<additional context or null>",
  "entry_time": "<HH:mm or null>"
}

Common Indonesian food calorie estimates (per serving):
- Nasi putih (1 porsi 150g): 195 kcal
- Mie ayam: 450 kcal
- Nasi goreng: 600 kcal
- Indomie goreng: 380 kcal
- Indomie kuah: 320 kcal
- Ayam goreng (1 potong): 260 kcal
- Bakso (1 mangkok): 350 kcal
- Sate ayam (10 tusuk): 400 kcal
- Gado-gado: 300 kcal
- Rendang (1 porsi): 450 kcal
- Nasi padang (nasi + lauk): 700 kcal
- Teh manis: 80 kcal
- Kopi susu: 120 kcal
- Es jeruk: 90 kcal
- Bubble tea: 350 kcal
- Gorengan (1 pcs): 150 kcal

3. intent: "saving" (user saving money)
{
  "intent": "saving",
  "confidence": <0.000-1.000>,
  "amount": <number in IDR>,
  "note": "<reason or goal>",
  "entry_time": null
}

4. intent: "query" (user asking about their data)
{
  "intent": "query",
  "confidence": <0.000-1.000>,
  "query_type": "<spending_today|spending_month|calories_today|summary|balance>"
}

5. intent: "general" (casual chat, not trackable)
{
  "intent": "general",
  "confidence": <0.000-1.000>,
  "message": "<brief friendly response in Bahasa Indonesia>"
}

═══ FEW-SHOT EXAMPLES ═══

User: "spent 50rb mie ayam"
→ {"intent": "expense", "confidence": 0.95, "amount": 50000, "category": "food_drink", "merchant": null, "note": "mie ayam", "entry_time": null}

User: "grab 30rb ke kantor"
→ {"intent": "expense", "confidence": 0.92, "amount": 30000, "category": "transport", "merchant": "Grab", "note": "ke kantor", "entry_time": null}

User: "beli kopi 25rb gopay"
→ {"intent": "expense", "confidence": 0.93, "amount": 25000, "category": "food_drink", "merchant": null, "note": "kopi (gopay)", "entry_time": null}

User: "makan nasi goreng"
→ {"intent": "meal", "confidence": 0.85, "food_item": "nasi goreng", "calories": 600, "is_calorie_estimated": true, "note": null, "entry_time": null}

User: "lunch mie ayam + es teh"
→ {"intent": "meal", "confidence": 0.82, "food_item": "mie ayam + es teh", "calories": 530, "is_calorie_estimated": true, "note": null, "entry_time": null}

User: "nabung 500rb"
→ {"intent": "saving", "confidence": 0.95, "amount": 500000, "note": "tabungan", "entry_time": null}

User: "save 1jt buat emergency fund"
→ {"intent": "saving", "confidence": 0.93, "amount": 1000000, "note": "emergency fund", "entry_time": null}

User: "berapa pengeluaran hari ini?"
→ {"intent": "query", "confidence": 0.95, "query_type": "spending_today"}

User: "halo butler"
→ {"intent": "general", "confidence": 0.98, "message": "Halo! Ada yang bisa Butler bantu? 😊"}

═══ RULES ═══
- Always return valid JSON — single object, never an array
- Amount must be a number (not string), converted to IDR
- For food with multiple items, combine calories
- confidence should reflect how certain you are about the parsing
- If amount is mentioned but unclear what type → default to "expense"
- If unsure about intent entirely → default to "general" with low confidence
- NEVER include personality or conversation — just data
PROMPT;
    }

    /**
     * Build Prompt B — the summary generator system prompt.
     * Generative, returns natural language, all personality.
     */
    private function buildSummaryPrompt(): string
    {
        return <<<PROMPT
Kamu adalah Butler, asisten harian pribadi yang ramah dan non-judgmental.
Tugas kamu: buat ringkasan harian dari data yang diberikan.

ATURAN:
1. Tulis dalam Bahasa Indonesia casual (gaul tapi sopan)
2. Maksimal 90 kata
3. HARUS akhiri dengan:
   - Status budget/kalori (jika ada goal)
   - Streak line (berapa hari berturut-turut logging)
4. Jangan menghakimi pengeluaran user
5. Boleh kasih insight singkat kalau ada pola menarik
6. Gunakan emoji secukupnya (jangan berlebihan)
7. Jangan ulang semua data mentah — rangkum dengan cerdas
8. Kalau tidak ada entries, kirim pesan re-engagement yang friendly

FORMAT OUTPUT:
Langsung tulis pesan ringkasannya, tanpa JSON, tanpa formatting tambahan.
Ini akan langsung dikirim ke user via Telegram.

CONTOH OUTPUT (jika ada data):
"Hari ini kamu spend Rp 68.000 — mostly makan (Rp 45k GoFood, Rp 23k lainnya). Budget masih aman, sisa Rp 132.000 💪

Kalori belum dicatat hari ini. Coba log makan siang kamu!

🔥 Streak: 4 hari berturut-turut logging! Keep it up!"

CONTOH OUTPUT (jika tidak ada data):
"Hei, belum ada catatan hari ini nih. Gimana hari kamu? Coba ketik pengeluaran terakhir sebelum tidur 😊"
PROMPT;
    }

    /**
     * Get the current prompt version for parser.
     */
    public function getParserPromptVersion(): string
    {
        return 'parse_v1';
    }

    /**
     * Get the current prompt version for summary.
     */
    public function getSummaryPromptVersion(): string
    {
        return 'summary_daily_v1';
    }
}
