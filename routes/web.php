<?php

use App\Http\Controllers\DashboardController;
use App\Http\Controllers\OnboardingController;
use Illuminate\Support\Facades\Route;

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
    Route::get('/dashboard',          [DashboardController::class, 'index'])->name('dashboard.index');
    Route::get('/dashboard/history',  [DashboardController::class, 'history'])->name('dashboard.history');
    Route::patch('/dashboard/entries/{entry}', [DashboardController::class, 'updateEntry'])->name('dashboard.entry.update');
});
