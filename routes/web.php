<?php

use App\Http\Controllers\AdminController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\OnboardingController;
use App\Http\Controllers\MonitoringController;
use Illuminate\Support\Facades\Route;

Route::get('/monitoring', [MonitoringController::class, 'index'])->name('monitoring.index');

// ── Onboarding (signed URL entry, session-based continuation) ────────────
Route::get('/setup/{telegram_id}', [OnboardingController::class, 'start'])
     ->name('onboarding.start')
     ->middleware('signed');

Route::get('/setup/{telegram_id}/profile',       [OnboardingController::class, 'profile'])->name('onboarding.profile');
Route::post('/setup/{telegram_id}/profile',      [OnboardingController::class, 'saveProfile'])->name('onboarding.profile.save');

Route::get('/setup/{telegram_id}/accounts',      [OnboardingController::class, 'accounts'])->name('onboarding.accounts');
Route::post('/setup/{telegram_id}/accounts',     [OnboardingController::class, 'saveAccounts'])->name('onboarding.accounts.save');

Route::get('/setup/{telegram_id}/budget',        [OnboardingController::class, 'budget'])->name('onboarding.budget');
Route::post('/setup/{telegram_id}/budget',       [OnboardingController::class, 'saveBudget'])->name('onboarding.budget.save');

Route::get('/setup/{telegram_id}/health',        [OnboardingController::class, 'health'])->name('onboarding.health');
Route::post('/setup/{telegram_id}/health',       [OnboardingController::class, 'saveHealth'])->name('onboarding.health.save');

Route::get('/setup/{telegram_id}/notifications', [OnboardingController::class, 'notifications'])->name('onboarding.notifications');
Route::post('/setup/{telegram_id}/notifications',[OnboardingController::class, 'saveNotifications'])->name('onboarding.notifications.save');

Route::get('/setup/{telegram_id}/done',          [OnboardingController::class, 'done'])->name('onboarding.done');

// ── Dashboard (signed URL auth → sliding session) ────────────────────────
Route::get('/dashboard/auth/{telegram_id}', [DashboardController::class, 'auth'])
     ->name('dashboard.auth')
     ->middleware('signed');

Route::middleware('dashboard.session')->group(function () {
    Route::get('/dashboard',              [DashboardController::class, 'index'])->name('dashboard.index');
    Route::get('/dashboard/history',      [DashboardController::class, 'history'])->name('dashboard.history');
    Route::get('/dashboard/spending',     [DashboardController::class, 'spending'])->name('dashboard.spending');
    Route::get('/dashboard/nutrition',    [DashboardController::class, 'nutrition'])->name('dashboard.nutrition');
    Route::get('/dashboard/insights',     [DashboardController::class, 'insights'])->name('dashboard.insights');
    Route::get('/dashboard/settings',     [DashboardController::class, 'settings'])->name('dashboard.settings');
    Route::post('/dashboard/settings',    [DashboardController::class, 'saveSettings'])->name('dashboard.settings.save');
    Route::patch('/dashboard/entries/{entry}', [DashboardController::class, 'updateEntry'])->name('dashboard.entry.update');

    // Impersonation leave
    Route::post('/admin/leave-impersonate', [AdminController::class, 'leaveImpersonate'])->name('admin.impersonate.leave');
});

Route::middleware(['dashboard.session', 'is_admin'])->prefix('admin')->group(function () {
    Route::get('/users',    [AdminController::class, 'index'])->name('admin.users.index');
    Route::get('/ai-logs',  [AdminController::class, 'aiLogs'])->name('admin.ai-logs.index');
    Route::post('/impersonate/{user}', [AdminController::class, 'impersonate'])->name('admin.impersonate');
});
