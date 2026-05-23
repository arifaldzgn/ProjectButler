<?php

use App\Services\DailySummaryService;
use App\Services\ReminderService;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// ── Daily Summary at 21:00 user timezone ────────────────────────────
// Per spec: "Daily summary at 21:00 user's timezone"
Schedule::call(function () {
    app(DailySummaryService::class)->sendAndStore();
})->dailyAt('21:00')
  ->timezone(config('butler.timezone'))
  ->description('Send daily summary to all users via Telegram');

// ── Behavior-based Reminders ────────────────────────────────────────
// Per spec: check behavior conditions periodically
Schedule::call(function () {
    app(ReminderService::class)->processBehaviorBasedReminders();
})->everyFifteenMinutes()
  ->description('Process behavior-based reminders (no-log nudges, re-engagement)');

// ── Time-based Reminders ────────────────────────────────────────────
Schedule::call(function () {
    app(ReminderService::class)->processTimeBasedReminders();
})->everyMinute()
  ->description('Process time-based reminders');

// ── Manual Summary Trigger ──────────────────────────────────────────
Artisan::command('butler:summary', function () {
    app(DailySummaryService::class)->sendAndStore();
    $this->info('Daily summaries sent.');
})->purpose('Manually trigger daily summaries for all users');
