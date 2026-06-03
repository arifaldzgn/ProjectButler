<?php

namespace App\Services;

use App\Models\Entry;
use App\Models\RecurringEntry;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

class RecurringEntryService
{
    private TelegramService $telegram;

    public function __construct(TelegramService $telegram)
    {
        $this->telegram = $telegram;
    }

    /**
     * Process all due recurring entries.
     * Called every hour from the scheduler.
     */
    public function processRecurringEntries(): void
    {
        $due = RecurringEntry::due()
            ->with('user')
            ->get();

        foreach ($due as $recurring) {
            try {
                $this->runRecurring($recurring);
            } catch (\Exception $e) {
                Log::error('RecurringEntry failed', [
                    'id'    => $recurring->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }

    private function runRecurring(RecurringEntry $recurring): void
    {
        $user = $recurring->user;
        if (!$user || $user->onboarding_step !== 'complete') {
            return;
        }

        $now = Carbon::now($user->timezone);

        // Create a confirmed entry directly (recurring = auto-confirmed)
        $data = [
            'user_id'          => $user->id,
            'type'             => $recurring->type,
            'amount'           => $recurring->amount,
            'food_item'        => in_array($recurring->type, ['meal']) ? $recurring->description : null,
            'note'             => $recurring->description,
            'category'         => $recurring->category_name ?? 'other',
            'category_id'      => $recurring->category_id,
            'source_fund_id'   => $recurring->account_id,
            'entry_time'       => $now,
            'ai_intent'        => 'recurring',
            'ai_confidence'    => 1.0,
            'ai_prompt_version'=> 'recurring_v1.0',
            'ai_raw_input'     => "[Recurring] {$recurring->description}",
            'confirmed_at'     => $now,
        ];

        $entry = Entry::create($data);

        // Advance next_run_at
        $recurring->advanceNextRun($now);

        // Notify user
        if ($user->telegram_chat_id) {
            $amountF = $recurring->amount
                ? ' — Rp ' . number_format($recurring->amount, 0, ',', '.')
                : '';
            $this->telegram->sendMessage(
                (string) $user->telegram_chat_id,
                "🔁 *Entri berulang dicatat otomatis:*\n_{$recurring->description}{$amountF}_\n\n"
                . "_{$recurring->getFrequencyLabel()} · " . $now->translatedFormat('d M Y, H:i') . "_"
            );
        }
    }

    /**
     * Create a recurring entry template for a user.
     */
    public function create(User $user, array $data): RecurringEntry
    {
        $now = Carbon::now($user->timezone);

        // Compute first next_run_at
        $nextRun = $this->computeFirstRun($data['frequency'], $data, $now);

        return RecurringEntry::create([
            'user_id'       => $user->id,
            'description'   => $data['description'],
            'amount'        => $data['amount'] ?? null,
            'type'          => $data['type'] ?? 'expense',
            'account_id'    => $data['account_id'] ?? null,
            'category_id'   => $data['category_id'] ?? null,
            'category_name' => $data['category_name'] ?? null,
            'frequency'     => $data['frequency'],
            'day_of_week'   => $data['day_of_week'] ?? null,
            'day_of_month'  => $data['day_of_month'] ?? null,
            'next_run_at'   => $nextRun,
            'is_active'     => true,
        ]);
    }

    private function computeFirstRun(string $frequency, array $data, Carbon $now): Carbon
    {
        return match ($frequency) {
            'daily'   => $now->copy()->addDay()->startOfDay()->addHours(8),
            'weekly'  => $now->copy()->next($data['day_of_week'] ?? Carbon::MONDAY)->startOfDay()->addHours(8),
            'monthly' => $this->nextMonthlyRun($data['day_of_month'] ?? $now->day, $now),
            default   => $now->copy()->addDay(),
        };
    }

    private function nextMonthlyRun(int $dayOfMonth, Carbon $now): Carbon
    {
        $target = $now->copy()->setDay(min($dayOfMonth, $now->daysInMonth))->startOfDay()->addHours(8);
        if ($target->lte($now)) {
            $next = $now->copy()->addMonth();
            $target = $next->setDay(min($dayOfMonth, $next->daysInMonth))->startOfDay()->addHours(8);
        }
        return $target;
    }
}
