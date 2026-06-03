<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class TelegramService
{
    private string $token;
    private string $baseUrl;
    private ?string $debugContext = null;

    public function __construct()
    {
        $this->token = config('butler.telegram.bot_token');
        $this->baseUrl = "https://api.telegram.org/bot{$this->token}";
    }

    public function setDebugContext(?string $context): void
    {
        $this->debugContext = $context;
    }

    private function applyDebugContext(string &$text): void
    {
        if ($this->debugContext) {
            $text .= "\n\n" . $this->debugContext;
            $this->debugContext = null; // Consume it so it only attaches to the first outgoing message
        }
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
        $this->applyDebugContext($text);
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
        $this->applyDebugContext($text);
        $keyboard = ['inline_keyboard' => [$buttons]];

        $payload = [
            'chat_id' => $chatId,
            'text' => $text,
            'reply_markup' => json_encode($keyboard),
        ];
        if ($parseMode) {
            $payload['parse_mode'] = $parseMode;
        }

        try {
            $response = Http::post("{$this->baseUrl}/sendMessage", $payload);

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
     * Send a multi-row inline keyboard (e.g. fund selection during onboarding).
     * Buttons are arranged 2 per row.
     */
    public function sendFundSelectionKeyboard(string $chatId, string $text, array $buttons): ?int
    {
        $this->applyDebugContext($text);
        $rows = array_chunk($buttons, 2);
        $keyboard = ['inline_keyboard' => $rows];

        try {
            $response = Http::post("{$this->baseUrl}/sendMessage", [
                'chat_id' => $chatId,
                'text' => $text,
                'parse_mode' => 'Markdown',
                'reply_markup' => json_encode($keyboard),
            ]);

            if (!$response->successful()) {
                $response = Http::post("{$this->baseUrl}/sendMessage", [
                    'chat_id' => $chatId,
                    'text' => $text,
                    'reply_markup' => json_encode($keyboard),
                ]);
            }

            return $response->json('result.message_id');
        } catch (\Exception $e) {
            Log::error('Telegram sendFundSelectionKeyboard exception', ['message' => $e->getMessage()]);
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

    /**
     * Edit an existing message text and replace its inline keyboard.
     */
    public function editMessageWithKeyboard(
        string $chatId,
        int $messageId,
        string $text,
        array $buttons,
        ?string $parseMode = 'Markdown'
    ): bool {
        $this->applyDebugContext($text);
        $keyboard = ['inline_keyboard' => $buttons];
        try {
            $payload = [
                'chat_id' => $chatId,
                'message_id' => $messageId,
                'text' => $text,
                'parse_mode' => $parseMode,
                'reply_markup' => json_encode($keyboard),
            ];

            $response = Http::post("{$this->baseUrl}/editMessageText", $payload);

            if (!$response->successful() && $parseMode) {
                $payload['parse_mode'] = null;
                $response = Http::post("{$this->baseUrl}/editMessageText", $payload);
            }

            return $response->successful();
        } catch (\Exception $e) {
            Log::error('Telegram editMessageWithKeyboard failed', ['message' => $e->getMessage()]);
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

    /**
     * Build a single [↩ Undo] button for a confirmed entry.
     * Embedded in the confirmation message.
     */
    public function buildUndoButton(string $undoToken): array
    {
        return [['text' => '↩ Undo', 'callback_data' => "undo:{$undoToken}"]];
    }

    /**
     * Send a message with an account selection inline keyboard.
     * Used when interaction_mode = needs_clarification.
     *
     * @param  string  $chatId
     * @param  int     $entryId
     * @param  array   $accounts   [ ['id' => 1, 'name' => 'GoPay'], ... ]
     */
    public function sendAccountSelectionKeyboard(
        string $chatId,
        int $entryId,
        array $accounts
    ): ?int {
        $buttons = [];
        foreach ($accounts as $account) {
            $buttons[] = [
                'text'          => $account['name'],
                'callback_data' => "acct_sel:{$entryId}:{$account['id']}",
            ];
        }

        // 3 buttons per row
        $rows = array_chunk($buttons, 3);

        $keyboard = ['inline_keyboard' => $rows];

        try {
            $response = Http::post("{$this->baseUrl}/sendMessage", [
                'chat_id'      => $chatId,
                'text'         => 'Pakai akun yang mana?',
                'reply_markup' => json_encode($keyboard),
            ]);
            return $response->json('result.message_id');
        } catch (\Exception $e) {
            Log::error('Telegram sendAccountSelectionKeyboard failed', ['message' => $e->getMessage()]);
            return null;
        }
    }

    /**
     * Send the consent gate question when behavioral confidence crosses 0.80.
     * Strict format per v2.1 spec: no emoji overload, no emotional phrasing.
     */
    public function sendConsentGate(
        string $chatId,
        string $subject,
        string $accountName,
        string $domain,
        string $domainSubject
    ): void {
        $text = "Aku lihat kamu biasanya pakai {$accountName} buat {$subject}.\nMau aku otomatisin ke depannya?";
        $buttons = [
            [
                ['text' => 'Boleh', 'callback_data' => "consent_yes:{$domain}:{$domainSubject}"],
                ['text' => 'Jangan', 'callback_data' => "consent_no:{$domain}:{$domainSubject}"],
            ]
        ];
        $keyboard = ['inline_keyboard' => $buttons];

        try {
            Http::post("{$this->baseUrl}/sendMessage", [
                'chat_id'      => $chatId,
                'text'         => $text,
                'reply_markup' => json_encode($keyboard),
            ]);
        } catch (\Exception $e) {
            Log::error('Telegram sendConsentGate failed', ['message' => $e->getMessage()]);
        }
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
        $fundName = $parsed['fund_name'] ?? null;
        $emoji = $this->getCategoryEmoji($category);
        $categoryLabel = $this->getCategoryLabel($category);

        $msg = "💸 *Pengeluaran terdeteksi:*\n\n"
            . "💰 Rp {$amount}\n"
            . "{$emoji} Kategori: {$categoryLabel}\n";

        if ($merchant) {
            $msg .= "🏪 Merchant: {$merchant}\n";
        }
        if ($fundName) {
            $msg .= "🏦 Sumber Dana: {$fundName}\n";
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

        // Macro breakdown if available
        $hasMacros = isset($parsed['protein_g']) || isset($parsed['carbs_g']) || isset($parsed['fat_g']);
        if ($hasMacros) {
            $p = $parsed['protein_g'] ?? 0;
            $c = $parsed['carbs_g'] ?? 0;
            $f = $parsed['fat_g'] ?? 0;
            $msg .= "\n💊 P:{$p}g · C:{$c}g · L:{$f}g _(est)_";
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
        $fundName = $parsed['fund_name'] ?? null;

        $msg = "💎 *Tabungan terdeteksi:*\n\n"
            . "💰 Rp {$amount}\n"
            . "📝 {$note}\n";

        if ($fundName) {
            $msg .= "📁 Dana: {$fundName}\n";
        }

        return $msg;
    }

    /**
     * Format income confirmation message.
     */
    public function formatIncomeConfirmation(array $parsed, float $confidence): string
    {
        $amount = number_format($parsed['amount'] ?? 0, 0, ',', '.');
        $source = $parsed['source'] ?? 'income';
        $fundName = $parsed['fund_name'] ?? null;
        $sourceLabel = match($source) {
            'gaji' => '💼 Gaji',
            'freelance' => '💻 Freelance',
            'bonus' => '🎁 Bonus',
            'transfer' => '💸 Transfer',
            default => '💰 Income',
        };

        $msg = "💰 *Income terdeteksi:*\n\n"
            . "{$sourceLabel}: Rp {$amount}\n";

        if ($fundName) {
            $msg .= "📥 Masuk Ke: {$fundName}\n";
        }

        if ($confidence < 0.75) {
            $msg .= "\n⚠️ _Butler kurang yakin. Cek dulu ya!_";
        }

        return $msg;
    }

    /**
     * Format bill payment confirmation.
     */
    public function formatBillPaymentConfirmation(array $parsed, float $confidence): string
    {
        $billName = $parsed['bill_name'] ?? 'Tagihan';
        $amount = isset($parsed['amount']) ? 'Rp ' . number_format($parsed['amount'], 0, ',', '.') : '(nominal tidak disebutkan)';

        $msg = "🧾 *Pembayaran tagihan terdeteksi:*\n\n"
            . "📋 {$billName}\n"
            . "💰 {$amount}\n";

        if ($confidence < 0.75) {
            $msg .= "\n⚠️ _Butler kurang yakin. Cek dulu ya!_";
        }

        return $msg;
    }

    /**
     * Format debt payment confirmation.
     */
    public function formatDebtPaymentConfirmation(array $parsed, float $confidence): string
    {
        $debtName = $parsed['debt_name'] ?? 'Cicilan';
        $amount = number_format($parsed['amount'] ?? 0, 0, ',', '.');

        $msg = "💳 *Pembayaran cicilan terdeteksi:*\n\n"
            . "📋 {$debtName}\n"
            . "💰 Rp {$amount}\n";

        if ($confidence < 0.75) {
            $msg .= "\n⚠️ _Butler kurang yakin. Cek dulu ya!_";
        }

        return $msg;
    }

    /**
     * Format sinking fund deposit confirmation.
     */
    public function formatSinkingDepositConfirmation(array $parsed, float $confidence): string
    {
        $fundName = $parsed['fund_name'] ?? 'Sinking Fund';
        $amount = number_format($parsed['amount'] ?? 0, 0, ',', '.');
        $deductedFrom = !empty($parsed['deducted_from']) ? " (dari {$parsed['deducted_from']})" : '';

        return "✈️ *Setoran sinking fund terdeteksi:*\n\n"
            . "📁 {$fundName}\n"
            . "💰 Rp {$amount}{$deductedFrom}\n";
    }

    /**
     * Format transfer confirmation.
     */
    public function formatTransferConfirmation(array $parsed, float $confidence): string
    {
        $amount = number_format($parsed['amount'] ?? 0, 0, ',', '.');
        $source = $parsed['source_fund'] ?? 'Tabungan';
        $target = $parsed['target_fund'] ?? 'dana lain';

        $msg = "🔄 *Transfer uang terdeteksi:*\n\n"
            . "💰 Rp {$amount}\n"
            . "📤 Dari: {$source}\n"
            . "📥 Ke: {$target}\n";

        if ($confidence < 0.75) {
            $msg .= "\n⚠️ _Butler kurang yakin. Cek dulu ya!_";
        }

        return $msg;
    }

    /**
     * Format confirmed entry message (after user confirms).
     * v2.1: Appends [↩ Undo] button and stores telegram_message_id on entry.
     */
    public function formatConfirmedMessage(string $type, array $parsed, int $todayTotal, ?int $remaining = null): string
    {
        $deductedFrom = !empty($parsed['deducted_from']) ? " (dari {$parsed['deducted_from']})" : '';
        return match ($type) {
            'expense'              => $this->formatExpenseConfirmed($parsed, $todayTotal, $remaining),
            'meal'                 => $this->formatMealConfirmed($parsed, $todayTotal, $remaining),
            'saving'               => $this->formatSavingConfirmed($parsed, $todayTotal),
            'income'               => 'Dicatat.\nIncome Rp ' . number_format($parsed['amount'] ?? 0, 0, ',', '.') . ' masuk.',
            'bill_payment'         => 'Tagihan ' . ($parsed['bill_name'] ?? 'Tagihan') . ' dibayar' . $deductedFrom . '.',
            'debt_payment'         => 'Cicilan ' . ($parsed['debt_name'] ?? 'Cicilan') . ' dibayar' . $deductedFrom . '.',
            'sinking_fund_deposit' => 'Setoran ke ' . ($parsed['fund_name'] ?? 'Dana') . ' disimpan' . $deductedFrom . '.',
            'transfer'             => 'Transfer Rp ' . number_format($parsed['amount'] ?? 0, 0, ',', '.') . ' selesai.',
            default                => 'Dicatat.',
        };
    }

    /**
     * Send a confirmed entry message with an [↩ Undo] button.
     * Returns the Telegram message_id so it can be stored on the entry.
     *
     * @param  string  $chatId
     * @param  string  $text        formatted message from formatConfirmedMessage()
     * @param  string  $undoToken   stored on entry.undo_token
     * @return int|null             telegram message_id
     */
    public function sendConfirmedWithUndo(string $chatId, string $text, string $undoToken): ?int
    {
        $buttons = $this->buildUndoButton($undoToken);
        $keyboard = ['inline_keyboard' => [$buttons]];

        try {
            $response = Http::post("{$this->baseUrl}/sendMessage", [
                'chat_id'      => $chatId,
                'text'         => $text,
                'parse_mode'   => 'Markdown',
                'reply_markup' => json_encode($keyboard),
            ]);

            if (!$response->successful()) {
                // Retry without markdown
                $response = Http::post("{$this->baseUrl}/sendMessage", [
                    'chat_id'      => $chatId,
                    'text'         => $text,
                    'reply_markup' => json_encode($keyboard),
                ]);
            }

            return $response->json('result.message_id');
        } catch (\Exception $e) {
            Log::error('Telegram sendConfirmedWithUndo failed', ['message' => $e->getMessage()]);
            return null;
        }
    }

    /**
     * Send a confirmed entry message with [↩ Undo] and [✏️ Edit] buttons.
     * The edit button is a URL that opens the dashboard.
     *
     * @return int|null  Telegram message_id
     */
    public function sendConfirmedWithUndoAndEdit(string $chatId, string $text, string $undoToken, string $editUrl, ?int $mealEntryId = null): ?int
    {
        $buttons = [
            $this->buildUndoButton($undoToken),
            ['text' => '✏️ Edit', 'url' => $editUrl],
        ];

        // Add calorie edit button for meal entries
        if ($mealEntryId) {
            $buttons[] = ['text' => '🔢 Edit Kalori', 'callback_data' => "cal_edit:{$mealEntryId}"];
        }

        $keyboard = ['inline_keyboard' => [$buttons]];

        try {
            $response = Http::post("{$this->baseUrl}/sendMessage", [
                'chat_id'      => $chatId,
                'text'         => $text,
                'parse_mode'   => 'Markdown',
                'reply_markup' => json_encode($keyboard),
            ]);

            if (!$response->successful()) {
                $response = Http::post("{$this->baseUrl}/sendMessage", [
                    'chat_id'      => $chatId,
                    'text'         => $text,
                    'reply_markup' => json_encode($keyboard),
                ]);
            }

            return $response->json('result.message_id');
        } catch (\Exception $e) {
            Log::error('Telegram sendConfirmedWithUndoAndEdit failed', ['message' => $e->getMessage()]);
            return null;
        }
    }

    /**
     * Edit a confirmed message to remove the undo button after expiry or use.
     * Leaves the original text intact, strips the keyboard.
     */
    public function stripUndoButton(string $chatId, int $messageId, string $newText): void
    {
        try {
            Http::post("{$this->baseUrl}/editMessageText", [
                'chat_id'      => $chatId,
                'message_id'   => $messageId,
                'text'         => $newText,
                'parse_mode'   => 'Markdown',
                'reply_markup' => json_encode(['inline_keyboard' => []]),
            ]);
        } catch (\Exception $e) {
            Log::error('Telegram stripUndoButton failed', ['message' => $e->getMessage()]);
        }
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
        $note   = $parsed['note'] ?? ($parsed['category'] ?? '-');
        $deductedFrom = !empty($parsed['deducted_from']) ? " (dari {$parsed['deducted_from']})" : '';

        $msg = "Tercatat: {$note} • Rp {$amount}{$deductedFrom}\n";
        $msg .= "Total hari ini: Rp {$todayF}";

        if ($remaining !== null) {
            $remainF = number_format(abs($remaining), 0, ',', '.');
            $msg .= $remaining >= 0
                ? "\nBudget sisa: Rp {$remainF} 🟢"
                : "\nMelebihi budget Rp {$remainF} ⚠️";
        }

        return $msg;
    }

    private function formatMealConfirmed(array $parsed, int $todayCalories, ?int $calorieGoal): string
    {
        $foodItem    = $parsed['food_item'] ?? '-';
        $calories    = $parsed['calories'] ?? 0;
        $isEstimated = $parsed['is_calorie_estimated'] ?? true;
        $src         = $isEstimated ? ' _(estimasi)_' : '';

        $msg = "{$foodItem} • {$calories} kcal{$src}\n";
        $msg .= "Total hari ini: {$todayCalories} kcal";

        if ($calorieGoal) {
            $remaining = $calorieGoal - $todayCalories;
            $ratio     = $todayCalories / max($calorieGoal, 1);

            if ($remaining <= 0) {
                // Over goal
                $overF = number_format(abs($remaining), 0, ',', '.');
                $msg  .= " / {$calorieGoal} kcal ⚠️";
                $msg  .= "\nMelebihi target {$overF} kcal.";
            } elseif ($ratio >= 0.80) {
                // 80–99 % of goal — approaching limit
                $remF = number_format($remaining, 0, ',', '.');
                $msg .= " / {$calorieGoal} kcal 🟡";
                $msg .= "\nSisa {$remF} kcal dari target hari ini.";
            } else {
                $msg .= " / {$calorieGoal} kcal 🟢";
            }
        }

        return $msg;
    }

    private function formatSavingConfirmed(array $parsed, int $totalSavings): string
    {
        $amount  = number_format($parsed['amount'] ?? 0, 0, ',', '.');
        $totalF  = number_format($totalSavings, 0, ',', '.');
        $note    = $parsed['fund_name'] ?? $parsed['note'] ?? 'Tabungan';

        return "Tabungan Rp {$amount} ke {$note} disimpan.\nTotal: Rp {$totalF}";
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
