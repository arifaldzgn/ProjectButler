<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class TelegramService
{
    private string $token;
    private string $baseUrl;

    public function __construct()
    {
        $this->token = config('butler.telegram.bot_token');
        $this->baseUrl = "https://api.telegram.org/bot{$this->token}";
    }

    // ════════════════════════════════════════════════════════════════════
    // CORE TELEGRAM METHODS
    // ════════════════════════════════════════════════════════════════════

    /**
     * Send a text message.
     * Per spec: DON'T retry more than twice.
     */
    public function sendMessage(string $chatId, string $text, ?string $parseMode = 'Markdown'): bool
    {
        return $this->sendWithRetry($chatId, $text, $parseMode);
    }

    /**
     * Send a message with an inline keyboard for confirmation.
     *
     * Per spec: send an inline keyboard after every parsed entry
     * (confirm / edit / cancel).
     */
    public function sendMessageWithInlineKeyboard(
        string $chatId,
        string $text,
        array $buttons,
        ?string $parseMode = 'Markdown'
    ): ?int {
        $keyboard = ['inline_keyboard' => [$buttons]];

        try {
            $response = Http::post("{$this->baseUrl}/sendMessage", [
                'chat_id' => $chatId,
                'text' => $text,
                'parse_mode' => $parseMode,
                'reply_markup' => json_encode($keyboard),
            ]);

            if (!$response->successful()) {
                // Retry without parse mode (Markdown might be broken)
                if ($parseMode) {
                    return $this->sendMessageWithInlineKeyboard($chatId, $text, $buttons, null);
                }
                Log::error('Telegram sendMessage with keyboard failed', [
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);
                return null;
            }

            return $response->json('result.message_id');
        } catch (\Exception $e) {
            Log::error('Telegram keyboard exception', ['message' => $e->getMessage()]);
            return null;
        }
    }

    /**
     * Answer a callback query (acknowledge button tap).
     */
    public function answerCallbackQuery(string $callbackQueryId, ?string $text = null): bool
    {
        try {
            $payload = ['callback_query_id' => $callbackQueryId];
            if ($text) {
                $payload['text'] = $text;
            }

            Http::post("{$this->baseUrl}/answerCallbackQuery", $payload);
            return true;
        } catch (\Exception $e) {
            Log::error('Telegram answerCallbackQuery failed', ['message' => $e->getMessage()]);
            return false;
        }
    }

    /**
     * Edit an existing message (used after button tap).
     */
    public function editMessage(string $chatId, int $messageId, string $text, ?string $parseMode = 'Markdown'): bool
    {
        try {
            $payload = [
                'chat_id' => $chatId,
                'message_id' => $messageId,
                'text' => $text,
                'parse_mode' => $parseMode,
            ];

            $response = Http::post("{$this->baseUrl}/editMessageText", $payload);

            if (!$response->successful() && $parseMode) {
                $payload['parse_mode'] = null;
                Http::post("{$this->baseUrl}/editMessageText", $payload);
            }

            return true;
        } catch (\Exception $e) {
            Log::error('Telegram editMessage failed', ['message' => $e->getMessage()]);
            return false;
        }
    }

    // ════════════════════════════════════════════════════════════════════
    // CONFIRMATION INLINE KEYBOARDS
    // ════════════════════════════════════════════════════════════════════

    /**
     * Build the standard confirmation buttons for an entry.
     */
    public function buildConfirmationButtons(int $entryId): array
    {
        return [
            ['text' => '✅ Simpan', 'callback_data' => "confirm:{$entryId}"],
            ['text' => '✏️ Edit', 'callback_data' => "edit:{$entryId}"],
            ['text' => '❌ Batal', 'callback_data' => "cancel:{$entryId}"],
        ];
    }

    // ════════════════════════════════════════════════════════════════════
    // MESSAGE FORMATTING
    // ════════════════════════════════════════════════════════════════════

    /**
     * Format expense confirmation message.
     * Per spec: confidence-based display.
     */
    public function formatExpenseConfirmation(array $parsed, float $confidence): string
    {
        $amount = number_format($parsed['amount'] ?? 0, 0, ',', '.');
        $category = $parsed['category'] ?? 'other';
        $merchant = $parsed['merchant'] ?? null;
        $note = $parsed['note'] ?? '-';
        $emoji = $this->getCategoryEmoji($category);
        $categoryLabel = $this->getCategoryLabel($category);

        $msg = "💸 *Pengeluaran terdeteksi:*\n\n"
            . "💰 Rp {$amount}\n"
            . "{$emoji} Kategori: {$categoryLabel}\n";

        if ($merchant) {
            $msg .= "🏪 Merchant: {$merchant}\n";
        }

        $msg .= "📝 {$note}\n";

        // Flag uncertain fields for lower confidence
        if ($confidence < 0.75) {
            $msg .= "\n⚠️ _Butler kurang yakin dengan parsing ini. Cek dulu ya!_";
        }

        return $msg;
    }

    /**
     * Format meal confirmation message.
     * Per spec: clearly mark AI calorie estimates.
     */
    public function formatMealConfirmation(array $parsed, float $confidence): string
    {
        $foodItem = $parsed['food_item'] ?? '-';
        $calories = $parsed['calories'] ?? 0;
        $isEstimated = $parsed['is_calorie_estimated'] ?? true;

        $msg = "🍽️ *Makanan terdeteksi:*\n\n"
            . "🥘 {$foodItem}\n"
            . "🔥 {$calories} kcal";

        if ($isEstimated) {
            $msg .= " _(estimasi)_";
        }

        $msg .= "\n";

        if ($confidence < 0.75) {
            $msg .= "\n⚠️ _Butler kurang yakin dengan parsing ini. Cek dulu ya!_";
        }

        return $msg;
    }

    /**
     * Format saving confirmation message.
     */
    public function formatSavingConfirmation(array $parsed): string
    {
        $amount = number_format($parsed['amount'] ?? 0, 0, ',', '.');
        $note = $parsed['note'] ?? 'Tabungan';

        return "💎 *Tabungan terdeteksi:*\n\n"
            . "💰 Rp {$amount}\n"
            . "📝 {$note}\n";
    }

    /**
     * Format confirmed entry message (after user confirms).
     */
    public function formatConfirmedMessage(string $type, array $parsed, int $todayTotal, ?int $remaining = null): string
    {
        return match ($type) {
            'expense' => $this->formatExpenseConfirmed($parsed, $todayTotal, $remaining),
            'meal' => $this->formatMealConfirmed($parsed, $todayTotal, $remaining),
            'saving' => $this->formatSavingConfirmed($parsed, $todayTotal),
            default => '✅ Dicatat!',
        };
    }

    /**
     * Format spending today query response.
     */
    public function formatSpendingTodayResponse(int $total, ?int $budgetRemaining = null): string
    {
        $formatted = number_format($total, 0, ',', '.');
        $msg = "💸 Total pengeluaran hari ini: *Rp {$formatted}*";

        if ($budgetRemaining !== null) {
            $remainF = number_format(abs($budgetRemaining), 0, ',', '.');
            if ($budgetRemaining >= 0) {
                $msg .= "\n💰 Sisa budget: Rp {$remainF}";
            } else {
                $msg .= "\n⚠️ Melebihi budget Rp {$remainF}!";
            }
        }

        return $msg;
    }

    /**
     * Format calories today query response.
     */
    public function formatCaloriesTodayResponse(int $total, ?int $calorieGoal = null): string
    {
        $msg = "🔥 Kalori hari ini: *{$total} kcal*";

        if ($calorieGoal) {
            $remaining = $calorieGoal - $total;
            $pct = min(round(($total / max($calorieGoal, 1)) * 100), 100);
            $filled = (int) round(($pct / 100) * 10);
            $bar = str_repeat('█', $filled) . str_repeat('░', 10 - $filled);
            $status = $remaining > 0 ? '✅' : '⚠️';
            $msg .= " / {$calorieGoal} kcal {$status}\n[{$bar}] {$pct}%";
        }

        return $msg;
    }

    // ════════════════════════════════════════════════════════════════════
    // ERROR RESPONSE TEMPLATES (per spec)
    // ════════════════════════════════════════════════════════════════════

    /**
     * Get error response for parse failure.
     * Per spec: Never show a technical error. Every failure has a recovery path.
     */
    public function getParseErrorResponse(): string
    {
        return "Hmm, Butler nggak nangkep yang ini. Coba format:\n"
             . "`50k makan siang` atau `grab 23rb`";
    }

    /**
     * Get error response for missing amount.
     */
    public function getAmountMissingResponse(string $name): string
    {
        return "Bayar berapa, {$name}? Aku butuh nominalnya dulu.";
    }

    /**
     * Get error response for unclear category.
     */
    public function getCategoryUnclearResponse(): string
    {
        return "Ini untuk apa? Makan, transport, atau belanja?";
    }

    /**
     * Get error response for low confidence.
     */
    public function getLowConfidenceResponse(): string
    {
        return "Hmm, Butler bingung nih. Coba format yang lebih jelas:\n"
             . "• Pengeluaran: `50k makan siang` atau `grab 23rb`\n"
             . "• Makanan: `makan nasi goreng` atau `lunch mie ayam`\n"
             . "• Tabungan: `nabung 500rb`";
    }

    // ════════════════════════════════════════════════════════════════════
    // PRIVATE HELPERS
    // ════════════════════════════════════════════════════════════════════

    private function formatExpenseConfirmed(array $parsed, int $todayTotal, ?int $remaining): string
    {
        $amount = number_format($parsed['amount'] ?? 0, 0, ',', '.');
        $todayF = number_format($todayTotal, 0, ',', '.');
        $emoji = $this->getCategoryEmoji($parsed['category'] ?? 'other');
        $note = $parsed['note'] ?? '-';

        $msg = "✅ *Pengeluaran disimpan!*\n\n"
            . "💸 Rp {$amount}\n"
            . "{$emoji} {$note}\n\n"
            . "📊 Total hari ini: Rp {$todayF}";

        if ($remaining !== null) {
            $remainF = number_format(abs($remaining), 0, ',', '.');
            if ($remaining >= 0) {
                $msg .= "\n💰 Sisa budget: Rp {$remainF}";
            } else {
                $msg .= "\n⚠️ Melebihi budget Rp {$remainF}!";
            }
        }

        return $msg;
    }

    private function formatMealConfirmed(array $parsed, int $todayCalories, ?int $calorieGoal): string
    {
        $foodItem = $parsed['food_item'] ?? '-';
        $calories = $parsed['calories'] ?? 0;
        $isEstimated = $parsed['is_calorie_estimated'] ?? true;
        $estimateNote = $isEstimated ? ' _(estimasi)_' : '';

        $msg = "✅ *Makanan disimpan!*\n\n"
            . "🥘 {$foodItem}\n"
            . "🔥 {$calories} kcal{$estimateNote}\n\n"
            . "📊 Total hari ini: {$todayCalories} kcal";

        if ($calorieGoal) {
            $remaining = $calorieGoal - $todayCalories;
            $pct = min(round(($todayCalories / max($calorieGoal, 1)) * 100), 100);
            $status = $remaining > 0 ? '✅' : '⚠️';
            $msg .= " / {$calorieGoal} kcal {$status}";
        }

        return $msg;
    }

    private function formatSavingConfirmed(array $parsed, int $totalSavings): string
    {
        $amount = number_format($parsed['amount'] ?? 0, 0, ',', '.');
        $totalF = number_format($totalSavings, 0, ',', '.');
        $note = $parsed['note'] ?? 'Tabungan';

        return "✅ *Tabungan disimpan!*\n\n"
            . "💎 Rp {$amount}\n"
            . "📝 {$note}\n\n"
            . "💰 Total tabungan: Rp {$totalF}";
    }

    /**
     * Send with retry — per spec: DON'T retry more than twice.
     */
    private function sendWithRetry(string $chatId, string $text, ?string $parseMode, int $attempt = 1): bool
    {
        try {
            $response = Http::post("{$this->baseUrl}/sendMessage", [
                'chat_id' => $chatId,
                'text' => $text,
                'parse_mode' => $parseMode,
            ]);

            if (!$response->successful()) {
                // First retry: try without parse mode (Markdown might be broken)
                if ($parseMode && $attempt <= 1) {
                    return $this->sendWithRetry($chatId, $text, null, $attempt + 1);
                }

                // Second retry: one more attempt after brief wait
                if ($attempt <= 2) {
                    usleep(500000); // 500ms
                    return $this->sendWithRetry($chatId, $text, $parseMode, $attempt + 1);
                }

                Log::error('Telegram sendMessage failed after retries', [
                    'status' => $response->status(),
                    'attempts' => $attempt,
                ]);
                return false;
            }

            return true;
        } catch (\Exception $e) {
            Log::error('Telegram exception', ['message' => $e->getMessage()]);
            return false;
        }
    }

    private function getCategoryEmoji(string $category): string
    {
        return match ($category) {
            'food_drink' => '🍔',
            'transport' => '🚗',
            'shopping' => '🛍️',
            'entertainment' => '🎮',
            'health' => '💊',
            'utilities' => '🏠',
            'education' => '📚',
            default => '📁',
        };
    }

    private function getCategoryLabel(string $category): string
    {
        return match ($category) {
            'food_drink' => 'Makan & Minum',
            'transport' => 'Transportasi',
            'shopping' => 'Belanja',
            'entertainment' => 'Hiburan',
            'health' => 'Kesehatan',
            'utilities' => 'Tagihan',
            'education' => 'Pendidikan',
            default => 'Lainnya',
        };
    }
}
