<?php

namespace App\Services;

use App\Models\DailySummary;
use App\Models\Entry;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

class DailySummaryService
{
    private EntryService $entryService;
    private AIService $ai;
    private AiLogService $aiLog;
    private TelegramService $telegram;

    public function __construct(
        EntryService $entryService,
        AIService $ai,
        AiLogService $aiLog,
        TelegramService $telegram
    ) {
        $this->entryService = $entryService;
        $this->ai = $ai;
        $this->aiLog = $aiLog;
        $this->telegram = $telegram;
    }

    /**
     * Send daily summaries to all onboarded users.
     *
     * Per spec:
     * - Query by users table (not by activity) — include users with zero entries
     * - Zero entries → send re-engagement nudge
     * - Non-zero → AI-generated summary via Prompt B
     * - Store snapshot + AI text in daily_summaries
     * - Update user.last_summary_sent_at
     * - Prevent duplicate sends
     */
    public function sendAndStore(): void
    {
        $users = User::where('onboarding_step', 'complete')->get();

        foreach ($users as $user) {
            try {
                $this->processForUser($user);
            } catch (\Exception $e) {
                Log::error('Daily summary failed for user', [
                    'user_id' => $user->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }

    /**
     * Process daily summary for a single user.
     */
    private function processForUser(User $user): void
    {
        $today = Carbon::now($user->timezone)->toDateString();

        // Prevent duplicate sends
        $existing = DailySummary::forUser($user->id)
            ->where('summary_date', $today)
            ->where('summary_type', 'daily')
            ->first();

        if ($existing && $existing->was_delivered) {
            return; // Already sent today
        }

        // Gather data
        $entries = $this->entryService->getTodayEntries($user);
        $totalSpent = $this->entryService->getTodaySpending($user);
        $totalCalories = $this->entryService->getTodayCalories($user);
        $totalSaved = $this->entryService->getTotalSavings($user);
        $budgetRemaining = $this->entryService->getBudgetRemaining($user);
        $streak = $user->streak;
        $entryCount = $entries->count();

        // Build context for AI Prompt B
        $context = $this->buildSummaryContext($user, $entries, $totalSpent, $totalCalories, $totalSaved, $budgetRemaining, $streak);

        // Generate summary text via AI
        $startTime = microtime(true);
        $summaryText = $this->ai->generateSummary($context);
        $latencyMs = (int) ((microtime(true) - $startTime) * 1000);

        // Log the AI call
        $this->aiLog->logSummaryCall(
            $user,
            json_encode($context),
            $summaryText,
            $latencyMs,
            $summaryText !== null
        );

        // Fallback if AI fails
        if (!$summaryText) {
            if ($entryCount === 0) {
                $summaryText = "Hei {$user->name}, nggak ada catatan hari ini. Coba ketik pengeluaran terakhir kamu sebelum tidur 😊";
            } else {
                $summaryText = $this->buildFallbackSummary($user, $totalSpent, $totalCalories, $budgetRemaining, $streak);
            }
        }

        // Store summary
        $summary = DailySummary::updateOrCreate(
            [
                'user_id' => $user->id,
                'summary_date' => $today,
                'summary_type' => 'daily',
            ],
            [
                'total_spent_idr' => $totalSpent,
                'total_calories' => $totalCalories,
                'total_saved_idr' => $totalSaved,
                'entry_count' => $entryCount,
                'budget_remaining' => $budgetRemaining,
                'streak_at_time' => $streak->log_current ?? 0,
                'ai_generated_text' => $summaryText,
                'ai_prompt_version' => $this->ai->getSummaryPromptVersion(),
            ]
        );

        // Deliver via Telegram
        $delivered = $this->telegram->sendMessage(
            (string) $user->telegram_chat_id,
            "📊 *Ringkasan Harian*\n\n" . $summaryText
        );

        // Update delivery status
        $summary->update([
            'was_delivered' => $delivered,
            'delivered_at' => $delivered ? now() : null,
            'delivery_error' => $delivered ? null : 'Telegram delivery failed',
        ]);

        if ($delivered) {
            $user->update(['last_summary_sent_at' => now()]);
        }
    }

    /**
     * Build the context object for Prompt B.
     * Matches the spec's "Context Butler Needs for Every AI Summary Call" section.
     */
    private function buildSummaryContext(
        User $user,
        \Illuminate\Database\Eloquent\Collection $entries,
        int $totalSpent,
        int $totalCalories,
        int $totalSaved,
        ?int $budgetRemaining,
        ?\App\Models\Streak $streak
    ): array {
        $today = Carbon::now($user->timezone);

        // Format entries for AI context
        $formattedEntries = $entries->map(function ($entry) {
            $data = [
                'type' => $entry->type,
                'entry_time' => Carbon::parse($entry->entry_time)->format('H:i'),
            ];

            if ($entry->type === 'expense') {
                $data['amount'] = $entry->amount;
                $data['category'] = $entry->category;
                $data['merchant'] = $entry->merchant;
            } elseif ($entry->type === 'meal') {
                $data['food_item'] = $entry->food_item;
                $data['calories'] = $entry->calories;
            } elseif ($entry->type === 'saving') {
                $data['amount'] = $entry->amount;
            }

            return $data;
        })->toArray();

        return [
            'user' => [
                'name' => $user->name,
                'daily_budget_idr' => $user->daily_budget_idr,
                'daily_calorie_goal' => $user->daily_calorie_goal,
            ],
            'date' => $today->translatedFormat('l, d F Y'), // Senin, 19 Mei 2026
            'entries' => $formattedEntries,
            'totals' => [
                'spent_idr' => $totalSpent,
                'budget_remaining_idr' => $budgetRemaining,
                'calories_consumed' => $totalCalories > 0 ? $totalCalories : null,
                'saved_idr' => $totalSaved,
            ],
            'streak' => [
                'log_current' => $streak->log_current ?? 0,
                'log_longest' => $streak->log_longest ?? 0,
            ],
        ];
    }

    /**
     * Build a fallback summary if AI fails.
     */
    private function buildFallbackSummary(
        User $user,
        int $totalSpent,
        int $totalCalories,
        ?int $budgetRemaining,
        ?\App\Models\Streak $streak
    ): string {
        $spentF = number_format($totalSpent, 0, ',', '.');
        $msg = "Hari ini kamu spend Rp {$spentF}.";

        if ($budgetRemaining !== null) {
            $remainF = number_format(abs($budgetRemaining), 0, ',', '.');
            $msg .= $budgetRemaining >= 0
                ? " Sisa budget Rp {$remainF}."
                : " Melebihi budget Rp {$remainF}!";
        }

        if ($totalCalories > 0) {
            $msg .= "\n🔥 Kalori: {$totalCalories} kcal";
            if ($user->daily_calorie_goal) {
                $msg .= " / {$user->daily_calorie_goal} kcal";
            }
        }

        $currentStreak = $streak->log_current ?? 0;
        if ($currentStreak > 0) {
            $msg .= "\n\n🔥 Streak: {$currentStreak} hari berturut-turut!";
        }

        return $msg;
    }
}
