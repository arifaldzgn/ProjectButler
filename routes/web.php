<?php

use App\Http\Controllers\AdminController;
use App\Http\Controllers\CategoryController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\OnboardingController;
use App\Http\Controllers\MonitoringController;
use App\Http\Controllers\ShortcutInstallController;
use Illuminate\Support\Facades\Route;

Route::get('/monitoring', [MonitoringController::class, 'index'])->name('monitoring.index');

// ── Shortcut install page (public — code is the credential) ─────────────────
Route::get('/shortcut/install/{code}', [ShortcutInstallController::class, 'show'])
     ->name('shortcut.install')
     ->where('code', '[A-Z0-9]{6}');

// ── Portfolio: Architecture Diagram (public, no auth) ────────────────────
Route::get('/portfolio/architecture', fn () => view('portfolio.architecture'))->name('portfolio.architecture');

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
    Route::get('/dashboard/distribution',  [DashboardController::class, 'distribution'])->name('dashboard.distribution');
    Route::get('/dashboard/cashflow',      [DashboardController::class, 'cashflow'])->name('dashboard.cashflow');
    Route::get('/dashboard/timeline',      [DashboardController::class, 'timeline'])->name('dashboard.timeline');
    Route::get('/dashboard/debts',         [DashboardController::class, 'debtManager'])->name('dashboard.debts');
    Route::get('/dashboard/help',         [DashboardController::class, 'help'])->name('dashboard.help');
    Route::get('/dashboard/memory',       [DashboardController::class, 'memory'])->name('dashboard.memory');
    Route::delete('/dashboard/memory/{memory}', [DashboardController::class, 'deleteMemory'])->name('dashboard.memory.delete');
    Route::get('/dashboard/settings',     [DashboardController::class, 'settings'])->name('dashboard.settings');
    Route::post('/dashboard/settings',    [DashboardController::class, 'saveSettings'])->name('dashboard.settings.save');
    Route::patch('/dashboard/entries/{entry}', [DashboardController::class, 'updateEntry'])->name('dashboard.entry.update');
    Route::post('/dashboard/categories',            [CategoryController::class, 'store'])->name('dashboard.categories.store');
    Route::patch('/dashboard/categories/{category}', [CategoryController::class, 'update'])->name('dashboard.categories.update');
    Route::delete('/dashboard/categories/{category}', [CategoryController::class, 'destroy'])->name('dashboard.categories.destroy');

    // Finance Review
    Route::prefix('dashboard/finance-review')->name('finance-review.')->group(function () {
        Route::get('/',             [\App\Http\Controllers\FinanceReviewController::class, 'show'])->name('index');
        Route::get('/step/{step}',  [\App\Http\Controllers\FinanceReviewController::class, 'step'])->name('step')->whereNumber('step');
        Route::post('/step/{step}', [\App\Http\Controllers\FinanceReviewController::class, 'saveStep'])->name('step.save')->whereNumber('step');
        Route::get('/result',       [\App\Http\Controllers\FinanceReviewController::class, 'result'])->name('result');
        Route::post('/recalculate', [\App\Http\Controllers\FinanceReviewController::class, 'recalculate'])->name('recalculate');
        Route::post('/reset',       [\App\Http\Controllers\FinanceReviewController::class, 'reset'])->name('reset');
    });

    // Impersonation leave
    Route::post('/admin/leave-impersonate', [AdminController::class, 'leaveImpersonate'])->name('admin.impersonate.leave');
});

Route::middleware(['dashboard.session', 'is_admin'])->prefix('admin')->group(function () {
    Route::get('/users',         [AdminController::class, 'index'])->name('admin.users.index');
    Route::get('/ai-logs',       [AdminController::class, 'aiLogs'])->name('admin.ai-logs.index');
    Route::get('/unrecognized',  [AdminController::class, 'unrecognized'])->name('admin.unrecognized.index');
    Route::get('/token-usage',   [AdminController::class, 'tokenUsage'])->name('admin.token-usage.index');
    Route::post('/impersonate/{user}', [AdminController::class, 'impersonate'])->name('admin.impersonate');
});
