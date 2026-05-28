<?php

use App\Services\BillService;
use App\Services\DebtService;
use App\Services\DailySummaryService;
use App\Services\ReminderService;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// ── Daily Summary — per-user preferred time, checked every minute ────
// DailySummaryService::sendAndStore() now filters by each user's summary_time
// so each user gets their summary at their chosen hour (default 21:00 WIB).
Schedule::call(function () {
    app(DailySummaryService::class)->sendAndStore();
})->everyMinute()
  ->description('Send daily summary — per-user preferred time');

// ── Behavior-based Reminders — every 15 min ──────────────────────────
Schedule::call(function () {
    app(ReminderService::class)->processBehaviorBasedReminders();
})->everyFifteenMinutes()
  ->description('No-log nudges, re-engagement reminders');

// ── Time-based Reminders — every minute ──────────────────────────────
Schedule::call(function () {
    app(ReminderService::class)->processTimeBasedReminders();
})->everyMinute()
  ->description('User-set time-based reminders');

// ── Setup-incomplete Reminders — daily 10:00 ─────────────────────────
// Per spec: fire once per condition, never more than 1/day
Schedule::call(function () {
    app(ReminderService::class)->processSetupIncompleteReminders();
})->dailyAt('10:00')
  ->timezone(config('butler.timezone'))
  ->description('Setup-incomplete reminders (income, bills, emergency fund, debt)');

// ── Bill Due Reminders — every minute, per-bill reminder_time ───────
// ReminderService::processBillDueReminders() respects each bill's reminder_time.
Schedule::call(function () {
    app(ReminderService::class)->processBillDueReminders();
})->everyMinute()
  ->description('Bill due reminders — per-bill reminder_time (default 09:00)');

// ── Debt Due Reminders — daily 09:00 ────────────────────────────────
Schedule::call(function () {
    app(ReminderService::class)->processDebtDueReminders();
})->dailyAt('09:00')
  ->timezone(config('butler.timezone'))
  ->description('Debt installment due reminders');

// ── Monthly Reset — 1st of month 00:05 ──────────────────────────────
// Reset this_month_paid flags for all bills and debts
Schedule::call(function () {
    app(BillService::class)->resetMonthlyPaymentStatus();
    app(DebtService::class)->resetMonthlyPaymentStatus();
})->monthlyOn(1, '00:05')
  ->timezone(config('butler.timezone'))
  ->description('Reset monthly bill/debt payment status');

// ── Manual Commands ──────────────────────────────────────────────────
Artisan::command('butler:summary', function () {
    app(DailySummaryService::class)->sendAndStore();
    $this->info('Daily summaries sent.');
})->purpose('Manually trigger daily summaries for all users');

Artisan::command('butler:reminders {type=all}', function (string $type) {
    $service = app(ReminderService::class);
    match ($type) {
        'behavior' => $service->processBehaviorBasedReminders(),
        'setup' => $service->processSetupIncompleteReminders(),
        'bills' => $service->processBillDueReminders(),
        'debts' => $service->processDebtDueReminders(),
        default => collect(['behavior', 'setup', 'bills', 'debts'])
            ->each(fn($t) => $service->{"process" . ucfirst($t) . ($t === 'behavior' ? 'Based' : ($t === 'setup' ? 'Incomplete' : 'Due')) . "Reminders"}()),
    };
    $this->info("Reminders processed: {$type}");
})->purpose('Manually trigger reminder processing');
