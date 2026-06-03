<?php

namespace App\Services;

use App\Models\BehavioralMemory;
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

    public function processTimeBasedReminders(): int
    {
        $sent = 0;
        $users = User::where('onboarding_step', 'complete')->get();

        foreach ($users as $user) {
            $now = Carbon::now($user->timezone);
            $currentTime = $now->format('H:i');
            $currentDay = strtolower($now->format('D'));

            $reminders = Reminder::forUser($user->id)->active()->timeBased()->get();
            foreach ($reminders as $reminder) {
                if (!$this->shouldFireTimeBased($reminder, $currentTime, $currentDay, $now)) continue;
                $message = $reminder->renderMessage($this->buildTemplateVariables($user));
                if ($this->telegram->sendMessage((string) $user->telegram_chat_id, $message)) {
                    $reminder->markTriggered();
                    $sent++;
                }
            }
        }
        return $sent;
    }

    public function processBehaviorBasedReminders(): int
    {
        $sent = 0;
        $users = User::where('onboarding_step', 'complete')->get();

        foreach ($users as $user) {
            $now = Carbon::now($user->timezone);
            $reminders = Reminder::forUser($user->id)->active()->behaviorBased()->get();
            foreach ($reminders as $reminder) {
                if ($reminder->wasTriggeredToday($user->timezone)) continue;
                if (!$this->shouldFireBehaviorBased($reminder, $user, $now)) continue;
                $message = $reminder->renderMessage($this->buildTemplateVariables($user));
                if ($this->telegram->sendMessage((string) $user->telegram_chat_id, $message)) {
                    $reminder->markTriggered();
                    $sent++;
                }
            }
        }
        return $sent;
    }

    /**
     * Setup-incomplete reminders — fire max ONCE per condition, max 1/day.
     */
    public function processSetupIncompleteReminders(): int
    {
        $sent = 0;
        $users = User::where('onboarding_step', 'complete')
            ->where('tracking_mode', '!=', 'calorie')->get();

        foreach ($users as $user) {
            $tz = $user->timezone ?? 'Asia/Jakarta';
            $todayStr = now()->timezone($tz)->toDateString();

            $alreadySentToday = Reminder::forUser($user->id)
                ->setupIncomplete()
                ->where('trigger_count', '>', 0)
                ->whereDate('last_triggered_at', $todayStr)
                ->exists();

            if ($alreadySentToday) continue;

            $reminders = Reminder::forUser($user->id)->active()->setupIncomplete()
                ->where('trigger_count', 0)->get();

            foreach ($reminders as $reminder) {
                $condition = $reminder->trigger_condition ?? [];
                $field = $condition['field'] ?? null;
                if (!$field) continue;

                if ($this->isSetupConditionMet($user, $field)) {
                    $reminder->update(['is_active' => false]);
                    continue;
                }

                $daysRequired = $condition['days_after_onboarding'] ?? 2;
                if (!$user->onboarding_complete_at) continue;
                $daysSince = Carbon::parse($user->onboarding_complete_at)->diffInDays(now());
                if ($daysSince < $daysRequired) continue;

                $message = $reminder->renderMessage($this->buildTemplateVariables($user));
                if ($this->telegram->sendMessage((string) $user->telegram_chat_id, $message)) {
                    $reminder->markTriggered();
                    $sent++;
                    break;
                }
            }
        }
        return $sent;
    }

    /**
     * Bill due reminders — check bills due within remind_days_before.
     * Respects each bill's optional reminder_time (defaults to system 09:00).
     */
    public function processBillDueReminders(): int
    {
        $sent = 0;
        $users = User::where('onboarding_step', 'complete')->get();

        foreach ($users as $user) {
            $tz       = $user->timezone ?? 'Asia/Jakarta';
            $nowLocal = now()->timezone($tz);
            $nowHm    = $nowLocal->format('H:i');
            $todayStr = $nowLocal->toDateString();

            $bills = $user->bills()->active()->unpaidThisMonth()->get()
                ->filter(fn($b) => $b->auto_remind && $b->isDueWithin($b->remind_days_before, $tz));

            foreach ($bills as $bill) {
                // Respect per-bill reminder_time; default to system '09:00'
                $billTime = $bill->reminder_time
                    ? Carbon::parse($bill->reminder_time)->format('H:i')
                    : '09:00';

                // Only fire within the correct minute window
                if ($nowHm !== $billTime) continue;

                // Don't re-nudge today
                $alreadyNudged = Reminder::forUser($user->id)
                    ->where('linked_bill_id', $bill->id)
                    ->whereDate('last_triggered_at', $todayStr)->exists();

                if ($alreadyNudged) continue;

                $amountF = 'Rp ' . number_format($bill->amount, 0, ',', '.');
                $msg = "⏰ *Tagihan jatuh tempo!*\n\n📋 {$bill->name}\n💰 {$amountF}\n📅 Tanggal {$bill->due_day}";

                if ($this->telegram->sendMessage((string) $user->telegram_chat_id, $msg)) {
                    $billReminder = Reminder::forUser($user->id)
                        ->where('linked_bill_id', $bill->id)->first();
                    if ($billReminder) {
                        $billReminder->markTriggered();
                    }
                    $sent++;
                }
            }
        }
        return $sent;
    }

    /**
     * Debt due reminders — check debts due within remind_days_before.
     */
    public function processDebtDueReminders(): int
    {
        $sent = 0;
        $users = User::where('onboarding_step', 'complete')->get();

        foreach ($users as $user) {
            $tz = $user->timezone ?? 'Asia/Jakarta';

            $debts = $user->debts()->active()->unpaidThisMonth()->get()
                ->filter(fn($d) => $d->auto_remind && $d->isDueWithin($d->remind_days_before, $tz));

            foreach ($debts as $debt) {
                $amountF = 'Rp ' . number_format($debt->monthly_installment, 0, ',', '.');
                $msg = "⏰ *Cicilan jatuh tempo!*\n\n📋 {$debt->name}\n💰 {$amountF}/bulan\n📅 Tanggal {$debt->due_day}";

                if ($this->telegram->sendMessage((string) $user->telegram_chat_id, $msg)) {
                    $sent++;
                }
            }
        }
        return $sent;
    }

    // ── Private helpers ─────────────────────────────────────────────────

    private function shouldFireTimeBased(Reminder $reminder, string $currentTime, string $currentDay, Carbon $now): bool
    {
        $allowedDays = array_map('trim', explode(',', $reminder->trigger_days ?? 'mon,tue,wed,thu,fri,sat,sun'));
        if (!in_array($currentDay, $allowedDays)) return false;
        if (!$reminder->trigger_time) return false;
        if (Carbon::parse($reminder->trigger_time)->format('H:i') !== $currentTime) return false;
        if ($reminder->last_triggered_at && Carbon::parse($reminder->last_triggered_at)->diffInMinutes($now) < 1) return false;
        return true;
    }

    private function shouldFireBehaviorBased(Reminder $reminder, User $user, Carbon $now): bool
    {
        $condition = $reminder->trigger_condition;
        if (!$condition || !isset($condition['type'])) return false;

        return match ($condition['type']) {
            'no_expense_log' => $this->checkNoLog($user, 'expense', $condition, $now),
            'no_meal_log' => $this->checkNoLog($user, 'meal', $condition, $now),
            'inactive_days' => $this->checkInactiveDays($user, $condition),
            default => false,
        };
    }

    private function checkNoLog(User $user, string $type, array $condition, Carbon $now): bool
    {
        if ($now->format('H:i') < ($condition['by_time'] ?? '20:00')) return false;
        $today = $now->toDateString();
        return !Entry::forUser($user->id)->where('type', $type)->confirmed()->forDate($today)->exists();
    }

    private function checkInactiveDays(User $user, array $condition): bool
    {
        if (!$user->last_active_at) return true;
        return Carbon::parse($user->last_active_at)->diffInDays(now()) >= ($condition['threshold'] ?? 2);
    }

    private function isSetupConditionMet(User $user, string $field): bool
    {
        return match ($field) {
            'income' => $user->has_income_set,
            'bills' => $user->has_bills_setup,
            'emergency_fund' => $user->has_emergency_fund,
            'debt' => $user->has_debt_declared,
            default => false,
        };
    }

    private function buildTemplateVariables(User $user): array
    {
        $streak = $user->streak;
        return [
            'name' => $user->name,
            'streak' => (string) ($streak->log_current ?? 0),
        ];
    }

    /**
     * Analyse user's historical logging hours (last 14 days) and return
     * the most common hour(s) with gaps — ideal reminder slots.
     *
     * Returns ['morning' => '07:00', 'evening' => '20:00'] or null for each.
     * Requires at least 14 confirmed entries before making a suggestion.
     *
     * Algorithm:
     *  1. Bucket entry created_at hours for the past 14 days.
     *  2. Find gaps (hours with 0 logs after a busy period → good reminder slot).
     *  3. Return up to 2 suggested times: one AM and one PM.
     */
    public function getOptimalReminderTime(User $user): array
    {
        $since = Carbon::now($user->timezone)->subDays(14);

        $entries = Entry::forUser($user->id)
            ->confirmed()
            ->where('entry_time', '>=', $since)
            ->get(['entry_time']);

        if ($entries->count() < 14) {
            return ['morning' => null, 'evening' => null, 'insufficient_data' => true];
        }

        // Build hour histogram (0-23)
        $histogram = array_fill(0, 24, 0);
        foreach ($entries as $entry) {
            $hour = Carbon::parse($entry->entry_time)->timezone($user->timezone)->hour;
            $histogram[$hour]++;
        }

        // Persist the full histogram as behavioral memory
        $this->observeLogTiming($user, $histogram);

        // Find gaps: hours with 0 activity that follow high-activity hours
        $morningSlot = $this->findGapInRange($histogram, 6, 11);   // 06:00–11:59
        $eveningSlot = $this->findGapInRange($histogram, 17, 22);  // 17:00–22:59

        return [
            'morning'          => $morningSlot ? sprintf('%02d:00', $morningSlot) : null,
            'evening'          => $eveningSlot ? sprintf('%02d:00', $eveningSlot) : null,
            'histogram'        => $histogram,
            'insufficient_data'=> false,
        ];
    }

    /**
     * Find the first hour in a range where activity is 0 or minimal.
     * Returns the hour number (0-23) or null.
     */
    private function findGapInRange(array $histogram, int $from, int $to): ?int
    {
        // If everything in the range is 0, just return the midpoint
        $rangeMax = max(array_slice($histogram, $from, $to - $from + 1));
        if ($rangeMax === 0) {
            return (int) round(($from + $to) / 2);
        }

        // Return the first hour in range with below-average activity
        $rangeTotal = array_sum(array_slice($histogram, $from, $to - $from + 1));
        $rangeAvg   = $rangeTotal / ($to - $from + 1);
        for ($h = $from; $h <= $to; $h++) {
            if ($histogram[$h] <= $rangeAvg * 0.5) {
                return $h;
            }
        }
        return null;
    }

    /**
     * Persist log_timing histogram to behavioral memory.
     */
    private function observeLogTiming(User $user, array $histogram): void
    {
        try {
            $memory = BehavioralMemory::where('user_id', $user->id)
                ->where('domain', BehavioralMemory::DOMAIN_LOG_TIMING)
                ->where('subject', 'hour_histogram')
                ->first();

            $value = ['histogram' => $histogram, 'days_sampled' => 14, 'updated_at' => now()->toDateString()];

            if ($memory) {
                $memory->update(['value' => $value, 'confidence' => 0.8, 'last_observed_at' => now()]);
            } else {
                BehavioralMemory::create([
                    'user_id'          => $user->id,
                    'domain'           => BehavioralMemory::DOMAIN_LOG_TIMING,
                    'subject'          => 'hour_histogram',
                    'value'            => $value,
                    'confidence'       => 0.8,
                    'consent_status'   => 'none',
                    'last_observed_at' => now(),
                ]);
            }
        } catch (\Exception $e) {
            Log::warning('Could not persist log_timing memory', ['error' => $e->getMessage()]);
        }
    }
}
