<?php

namespace App\Http\Controllers;

use App\Jobs\SeedBehavioralMemoryFromOnboarding;
use App\Jobs\SendOnboardingCompleteNotification;
use App\Models\Account;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\URL;
use Illuminate\View\View;

class OnboardingController extends Controller
{
    // ── Entry Point ─────────────────────────────────────────────────────

    /**
     * Signed URL entry point — validates token, creates session, resumes step.
     */
    public function start(Request $request, int $telegram_id): RedirectResponse
    {
        // 'signed' middleware already validated URL integrity + expiry
        $user = User::where('telegram_chat_id', $telegram_id)->firstOrFail();

        // Already complete — redirect to dashboard
        if ($user->onboarding_step === 'complete') {
            return redirect()->route('dashboard.index');
        }

        // Mark as opened + record timestamp
        $user->update([
            'onboarding_step'       => 'webview_opened',
            'onboarding_started_at' => $user->onboarding_started_at ?? now(),
        ]);

        // Store user identity in session
        session([
            'onboarding_user_id'    => $user->id,
            'onboarding_telegram_id' => $telegram_id,
        ]);

        // Resume from current step if mid-flow
        return $this->redirectToCurrentStep($user, $telegram_id);
    }

    private function redirectToCurrentStep(User $user, int $telegramId): RedirectResponse
    {
        return match ($user->onboarding_step) {
            'accounts_done'       => redirect()->route('onboarding.budget',        $telegramId),
            'budget_done'         => redirect()->route('onboarding.health',         $telegramId),
            'health_done'         => redirect()->route('onboarding.notifications',  $telegramId),
            'notifications_done'  => redirect()->route('onboarding.done',           $telegramId),
            default               => redirect()->route('onboarding.profile',        $telegramId),
        };
    }

    // ── Step 1: Profile ─────────────────────────────────────────────────

    public function profile(int $telegram_id): View
    {
        $user = $this->getOnboardingUser();
        return view('onboarding.profile', compact('user', 'telegram_id'));
    }

    public function saveProfile(Request $request, int $telegram_id): RedirectResponse
    {
        $user = $this->getOnboardingUser();

        $data = $request->validate([
            'name'     => 'required|string|max:128',
            'currency' => 'required|in:IDR,USD,SGD,MYR',
            'timezone' => 'required|timezone',
        ]);

        $user->update([
            ...$data,
            'onboarding_step' => 'profile_done',
        ]);

        return redirect()->route('onboarding.accounts', $telegram_id);
    }

    // ── Step 2: Accounts ─────────────────────────────────────────────────

    public function accounts(int $telegram_id): View
    {
        $user = $this->getOnboardingUser();
        return view('onboarding.accounts', compact('user', 'telegram_id'));
    }

    public function saveAccounts(Request $request, int $telegram_id): RedirectResponse
    {
        $user = $this->getOnboardingUser();

        $validated = $request->validate([
            'accounts'              => 'required|array|min:1',
            'accounts.*.name'       => 'required|string|max:128',
            'accounts.*.type'       => 'required|in:bank,ewallet,cash,other',
            'accounts.*.balance'    => 'nullable|integer|min:0',
            'default_account_index' => 'required|integer|min:0',
        ]);

        $defaultIndex     = (int) $validated['default_account_index'];
        $defaultAccountId = null;

        foreach ($validated['accounts'] as $index => $accountData) {
            $isDefault = ($index === $defaultIndex);

            $account = Account::create([
                'user_id'            => $user->id,
                'name'               => $accountData['name'],
                'type'               => $accountData['type'],
                'current_balance'    => $accountData['balance'] ?? 0,
                'initial_balance'    => $accountData['balance'] ?? 0,
                'is_default'         => $isDefault,
                'is_active'          => true,
            ]);

            if ($isDefault) {
                $defaultAccountId = $account->id;
            }
        }

        // Write default_account_id to users — this feeds the resolve layer
        $user->update([
            'default_account_id' => $defaultAccountId,
            'onboarding_step'    => 'accounts_done',
        ]);

        return redirect()->route('onboarding.budget', $telegram_id);
    }

    // ── Step 3: Budget ───────────────────────────────────────────────────

    public function budget(int $telegram_id): View
    {
        $user = $this->getOnboardingUser();
        return view('onboarding.budget', compact('user', 'telegram_id'));
    }

    public function saveBudget(Request $request, int $telegram_id): RedirectResponse
    {
        $user = $this->getOnboardingUser();

        if ($request->has('skip')) {
            $user->update(['onboarding_step' => 'budget_done']);
            return redirect()->route('onboarding.health', $telegram_id);
        }

        $validated = $request->validate([
            'monthly_budget_idr' => 'nullable|integer|min:0',
            'daily_budget_idr'   => 'nullable|integer|min:0',
        ]);

        $user->update([
            ...$validated,
            'onboarding_step' => 'budget_done',
        ]);

        return redirect()->route('onboarding.health', $telegram_id);
    }

    // ── Step 4: Health ───────────────────────────────────────────────────

    public function health(int $telegram_id): View
    {
        $user = $this->getOnboardingUser();
        return view('onboarding.health', compact('user', 'telegram_id'));
    }

    public function saveHealth(Request $request, int $telegram_id): RedirectResponse
    {
        $user = $this->getOnboardingUser();

        if ($request->has('skip')) {
            $user->update(['onboarding_step' => 'health_done']);
            return redirect()->route('onboarding.notifications', $telegram_id);
        }

        $validated = $request->validate([
            'calorie_tracking' => 'nullable|boolean',
            'calorie_goal'     => 'nullable|integer|min:500|max:5000',
            'health_goal'      => 'nullable|in:maintain,lose,gain,protein',
        ]);

        $trackCalories = $request->boolean('calorie_tracking');

        $user->update([
            'tracking_mode'     => $trackCalories ? 'both' : 'finance',
            'daily_calorie_goal' => $trackCalories ? ($validated['calorie_goal'] ?? null) : null,
            'health_goal'        => $validated['health_goal'] ?? null,
            'onboarding_step'    => 'health_done',
        ]);

        return redirect()->route('onboarding.notifications', $telegram_id);
    }

    // ── Step 5: Notifications ─────────────────────────────────────────────

    public function notifications(int $telegram_id): View
    {
        $user = $this->getOnboardingUser();
        return view('onboarding.notifications', compact('user', 'telegram_id'));
    }

    public function saveNotifications(Request $request, int $telegram_id): RedirectResponse
    {
        $user = $this->getOnboardingUser();

        if ($request->has('skip')) {
            $user->update([
                'daily_summary_enabled' => true,
                'summary_time'          => '21:00:00',
                'onboarding_step'       => 'notifications_done',
            ]);
            return redirect()->route('onboarding.done', $telegram_id);
        }

        $validated = $request->validate([
            'daily_summary' => 'nullable|boolean',
            'summary_time'  => 'nullable|date_format:H:i',
        ]);

        $user->update([
            'daily_summary_enabled' => $request->boolean('daily_summary', true),
            'summary_time'          => ($validated['summary_time'] ?? '21:00') . ':00',
            'onboarding_step'       => 'notifications_done',
        ]);

        return redirect()->route('onboarding.done', $telegram_id);
    }

    // ── Done ─────────────────────────────────────────────────────────────

    public function done(int $telegram_id): View
    {
        $user = $this->getOnboardingUser();

        // Finalize onboarding
        $user->update([
            'onboarding_step'        => 'complete',
            'onboarding_complete_at' => now(),
        ]);

        // Seed behavioral memory from onboarding data (queue: low)
        SeedBehavioralMemoryFromOnboarding::dispatch($user)->onQueue('low');

        // Notify user in Telegram (queue: default)
        SendOnboardingCompleteNotification::dispatch($user)->onQueue('default');

        // Clear session
        session()->forget(['onboarding_user_id', 'onboarding_telegram_id']);

        return view('onboarding.done', [
            'user'         => $user,
            'bot_username' => config('butler.telegram_bot_username'),
        ]);
    }

    // ── Private Helpers ───────────────────────────────────────────────────

    /**
     * Resolve user from session. Aborts 403 if session expired.
     */
    private function getOnboardingUser(): User
    {
        $userId = session('onboarding_user_id');
        abort_if(!$userId, 403, 'Session expired. Please request a new setup link from Telegram.');
        return User::findOrFail($userId);
    }
}
