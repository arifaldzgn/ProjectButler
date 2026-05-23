<?php

namespace App\Services;

use App\Models\Entry;
use App\Models\Reminder;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

class ReminderService
{
    private TelegramService $telegram;
    private EntryService $entries;

    public function __construct(TelegramService $telegram, EntryService $entries)
    {
        $this->telegram = $telegram;
        $this->entries = $entries;
    }

    // ════════════════════════════════════════════════════════════════════
    // TIME-BASED REMINDERS
    // ════════════════════════════════════════════════════════════════════

    /**
     * Process time-based reminders that should fire now.
     */
    public function processTimeBasedReminders(): int
    {
        $sent = 0;

        $users = User::where('onboarding_step', 'complete')->get();

        foreach ($users as $user) {
            $now = Carbon::now($user->timezone);
            $currentTime = $now->format('H:i');
            $currentDay = strtolower($now->format('D')); // mon, tue, etc.

            $reminders = Reminder::forUser($user->id)
                ->active()
                ->timeBased()
                ->get();

            foreach ($reminders as $reminder) {
                if (!$this->shouldFireTimeBased($reminder, $currentTime, $currentDay, $now)) {
                    continue;
                }

                $variables = $this->buildTemplateVariables($user);
                $message = $reminder->renderMessage($variables);

                if ($this->telegram->sendMessage((string) $user->telegram_chat_id, $message)) {
                    $reminder->markTriggered();
                    $sent++;
                }
            }
        }

        return $sent;
    }

    // ════════════════════════════════════════════════════════════════════
    // BEHAVIOR-BASED REMINDERS
    // ════════════════════════════════════════════════════════════════════

    /**
     * Process behavior-based reminders.
     *
     * Per spec:
     * - no_expense_log: no expense entries today by a certain time
     * - no_meal_log: no meal entries today by a certain time
     * - inactive_days: no activity for N days
     */
    public function processBehaviorBasedReminders(): int
    {
        $sent = 0;

        $users = User::where('onboarding_step', 'complete')->get();

        foreach ($users as $user) {
            $now = Carbon::now($user->timezone);

            $reminders = Reminder::forUser($user->id)
                ->active()
                ->behaviorBased()
                ->get();

            foreach ($reminders as $reminder) {
                if (!$this->shouldFireBehaviorBased($reminder, $user, $now)) {
                    continue;
                }

                // Don't re-trigger if already triggered today
                if ($reminder->last_triggered_at &&
                    Carbon::parse($reminder->last_triggered_at)->timezone($user->timezone)->isToday()) {
                    continue;
                }

                $variables = $this->buildTemplateVariables($user);
                $message = $reminder->renderMessage($variables);

                if ($this->telegram->sendMessage((string) $user->telegram_chat_id, $message)) {
                    $reminder->markTriggered();
                    $sent++;
                }
            }
        }

        if ($sent > 0) {
            Log::info("Processed {$sent} behavior-based reminders");
        }

        return $sent;
    }

    // ════════════════════════════════════════════════════════════════════
    // PRIVATE HELPERS
    // ════════════════════════════════════════════════════════════════════

    private function shouldFireTimeBased(Reminder $reminder, string $currentTime, string $currentDay, Carbon $now): bool
    {
        // Check day of week
        $allowedDays = array_map('trim', explode(',', $reminder->trigger_days ?? ''));
        if (!in_array($currentDay, $allowedDays)) {
            return false;
        }

        // Check time (within 1-minute window)
        if (!$reminder->trigger_time) {
            return false;
        }

        $triggerTime = Carbon::parse($reminder->trigger_time)->format('H:i');
        if ($currentTime !== $triggerTime) {
            return false;
        }

        // Don't re-trigger if already fired this minute
        if ($reminder->last_triggered_at) {
            $lastTriggered = Carbon::parse($reminder->last_triggered_at);
            if ($lastTriggered->diffInMinutes($now) < 1) {
                return false;
            }
        }

        return true;
    }

    private function shouldFireBehaviorBased(Reminder $reminder, User $user, Carbon $now): bool
    {
        $condition = $reminder->trigger_condition;
        if (!$condition || !isset($condition['type'])) {
            return false;
        }

        return match ($condition['type']) {
            'no_expense_log' => $this->checkNoLogCondition($user, 'expense', $condition, $now),
            'no_meal_log' => $this->checkNoLogCondition($user, 'meal', $condition, $now),
            'inactive_days' => $this->checkInactiveDays($user, $condition),
            default => false,
        };
    }

    private function checkNoLogCondition(User $user, string $type, array $condition, Carbon $now): bool
    {
        $byTime = $condition['by_time'] ?? '20:00';

        // Only fire after the specified time
        if ($now->format('H:i') < $byTime) {
            return false;
        }

        // Check if there are any confirmed entries of this type today
        $today = $now->toDateString();
        $hasEntries = Entry::forUser($user->id)
            ->where('type', $type)
            ->confirmed()
            ->forDate($today)
            ->exists();

        return !$hasEntries;
    }

    private function checkInactiveDays(User $user, array $condition): bool
    {
        $threshold = $condition['threshold'] ?? 2;

        if (!$user->last_active_at) {
            return true; // Never been active after onboarding
        }

        $daysSinceActive = Carbon::parse($user->last_active_at)->diffInDays(now());

        return $daysSinceActive >= $threshold;
    }

    private function buildTemplateVariables(User $user): array
    {
        $streak = $user->streak;

        return [
            'name' => $user->name,
            'streak' => (string) ($streak->log_current ?? 0),
            'budget_remaining' => $user->daily_budget_idr
                ? 'Rp ' . number_format($this->entries->getBudgetRemaining($user) ?? 0, 0, ',', '.')
                : 'nggak di-set',
        ];
    }
}
