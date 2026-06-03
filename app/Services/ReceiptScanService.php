<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Receipt Photo Scanning Service
 *
 * Downloads a Telegram photo by file_id, sends it to a vision model (via OpenRouter),
 * and returns structured entry data (merchant, amount, category, items, date).
 *
 * Extracted data flows into the standard entry creation pipeline — same confirmation
 * message as any other entry (user sees ✅ / ❌ buttons).
 */
class ReceiptScanService
{
    private OpenRouterClient $client;

    public function __construct(OpenRouterClient $client)
    {
        $this->client = $client;
    }

    /**
     * Download a Telegram photo and extract receipt data using vision AI.
     *
     * @param  string  $fileId     Telegram file_id (highest resolution)
     * @param  string  $caption    Optional caption from the user (hint text)
     * @return array|null          Structured receipt data or null on failure
     */
    public function extractFromTelegramPhoto(string $fileId, string $caption = ''): ?array
    {
        // Step 1: Resolve file path via Telegram Bot API
        $token   = config('butler.telegram_bot_token');
        $fileRes = Http::timeout(10)->get("https://api.telegram.org/bot{$token}/getFile", [
            'file_id' => $fileId,
        ])->json();

        $filePath = $fileRes['result']['file_path'] ?? null;
        if (!$filePath) {
            Log::warning('ReceiptScan: could not get file path', ['file_id' => $fileId]);
            return null;
        }

        // Step 2: Download photo bytes
        $imageResponse = Http::timeout(20)->get("https://api.telegram.org/file/bot{$token}/{$filePath}");
        if (!$imageResponse->successful()) {
            Log::warning('ReceiptScan: photo download failed', ['file_path' => $filePath]);
            return null;
        }

        $imageBase64 = base64_encode($imageResponse->body());
        $mimeType    = $imageResponse->header('Content-Type') ?: 'image/jpeg';

        // Step 3: Vision AI extraction
        $systemPrompt = $this->buildReceiptPrompt();
        $userText     = $caption ? "User note: {$caption}" : '';

        try {
            $response = $this->client->generateVisionJson($systemPrompt, $imageBase64, $mimeType, $userText);
        } catch (\Exception $e) {
            Log::error('ReceiptScan: vision AI failed', ['error' => $e->getMessage()]);
            return null;
        }

        if (!$response || !isset($response['data'])) {
            return null;
        }

        return $response['data'];
    }

    /**
     * Map extracted receipt data to the standard parsed entry array
     * (same shape that MessageRouter expects from AI parse).
     */
    public function mapToEntryParsed(array $receiptData): array
    {
        return [
            'intent'     => 'log_expense',
            'confidence' => 0.85,
            'amount'     => $this->parseAmount($receiptData['total'] ?? null),
            'category'   => $receiptData['category'] ?? 'other',
            'merchant'   => $receiptData['merchant'] ?? null,
            'note'       => $receiptData['note'] ?? ($receiptData['merchant'] ?? 'Struk'),
            'entry_time' => null,
            '_from_receipt' => true,
        ];
    }

    private function parseAmount($raw): int
    {
        if (is_numeric($raw)) {
            return (int) $raw;
        }
        // Strip non-numeric characters (Rp, commas, dots used as thousands separators)
        $clean = preg_replace('/[^0-9]/', '', (string) $raw);
        return (int) $clean;
    }

    private function buildReceiptPrompt(): string
    {
        return <<<PROMPT
You are a receipt OCR extractor for a personal finance app.
Analyze the receipt image and return a JSON object with these fields:

{
  "merchant": "<store/restaurant name or null>",
  "total": <total amount as integer, no currency symbol>,
  "category": "<food_drink|transport|shopping|health|utilities|education|other>",
  "items": [{"name": "<item>", "price": <integer>}],
  "receipt_date": "<YYYY-MM-DD or null>",
  "note": "<brief description>",
  "confidence": <0.0-1.0>
}

RULES:
- `total` must be the final total (after tax/discount), as an integer (IDR, no decimals).
- If multiple amounts: use the largest or the one labeled "TOTAL", "JUMLAH", "GRAND TOTAL".
- If the image is NOT a receipt, set confidence < 0.4 and all other fields null.
- Return valid JSON only. No commentary.
PROMPT;
    }
}
