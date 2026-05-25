# Project Butler — Webview Implementation Guide

> Covers: signed URL flow, onboarding pages, dashboard auth,
> session handling, Telegram ↔ webview handoff, routes + controllers.
> Stack: Laravel · Blade · Alpine.js

---

## Overview

The webview has two distinct modes:

```
ONBOARDING   One-time setup. Triggered by /start.
             Signed URL, expires 10 minutes, single-use.
             Entry point: /setup/{token}

DASHBOARD    Persistent access after onboarding.
             Signed URL on demand, expires 30 minutes.
             Entry point: /dashboard (via signed link)
```

Both use signed URLs. Neither uses passwords.
The user's identity is always `telegram_chat_id`.

---

## The Full Handoff Flow

```
User sends /start in Telegram
         │
         ▼
Bot checks: does this telegram_chat_id exist in users?
         │
    NO ──┤── Create user record (onboarding_step = 'pending')
         │   Generate signed onboarding URL
         │   Send Telegram message with [Mulai Setup] button
         │
   YES ──┤── Is onboarding_complete_at set?
         │
    NO ──┤── Resend onboarding URL (regenerate, 10-min window)
    YES ──┘── Send greeting + usage examples
```

---

## Part 1 — Onboarding (One-Time Setup)

### 1.1 Signed URL Generation

In your Telegram webhook handler, when a new user hits `/start`:

```php
// TelegramWebhookController.php

use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;

private function handleStart(int $chatId): void
{
    $user = User::firstOrCreate(
        ['telegram_chat_id' => $chatId],
        [
            'telegram_username'  => $this->update['message']['from']['username'] ?? null,
            'onboarding_step'    => 'pending',
            'timezone'           => 'Asia/Jakarta', // default, overridden in webview
        ]
    );

    if ($user->onboarding_complete_at) {
        $this->sendWelcomeBack($user);
        return;
    }

    // Generate signed URL — tied to telegram_chat_id, expires 10 min
    $url = URL::temporarySignedRoute(
        'onboarding.start',
        now()->addMinutes(10),
        ['telegram_id' => $chatId]
    );

    $this->telegram->sendMessage([
        'chat_id' => $chatId,
        'text'    => "Halo! Aku Butler, asisten keuangan harianmu.\nSetup butuh sekitar 3 menit.",
        'reply_markup' => json_encode([
            'inline_keyboard' => [[
                ['text' => '🚀 Mulai Setup', 'url' => $url]
            ]]
        ])
    ]);
}
```

### 1.2 Onboarding Routes

```php
// routes/web.php

Route::get('/setup/{telegram_id}', [OnboardingController::class, 'start'])
    ->name('onboarding.start')
    ->middleware('signed');                          // validates signature + expiry

Route::get('/setup/{telegram_id}/profile',          [OnboardingController::class, 'profile'])->name('onboarding.profile');
Route::post('/setup/{telegram_id}/profile',         [OnboardingController::class, 'saveProfile']);

Route::get('/setup/{telegram_id}/accounts',         [OnboardingController::class, 'accounts'])->name('onboarding.accounts');
Route::post('/setup/{telegram_id}/accounts',        [OnboardingController::class, 'saveAccounts']);

Route::get('/setup/{telegram_id}/budget',           [OnboardingController::class, 'budget'])->name('onboarding.budget');
Route::post('/setup/{telegram_id}/budget',          [OnboardingController::class, 'saveBudget']);

Route::get('/setup/{telegram_id}/health',           [OnboardingController::class, 'health'])->name('onboarding.health');
Route::post('/setup/{telegram_id}/health',          [OnboardingController::class, 'saveHealth']);

Route::get('/setup/{telegram_id}/notifications',    [OnboardingController::class, 'notifications'])->name('onboarding.notifications');
Route::post('/setup/{telegram_id}/notifications',   [OnboardingController::class, 'saveNotifications']);

Route::get('/setup/{telegram_id}/done',             [OnboardingController::class, 'done'])->name('onboarding.done');
```

All routes after `/setup/{telegram_id}` use the `telegram_id` path param.
The signed middleware only validates the first entry point.
After that, identity is carried via an encrypted session cookie.

### 1.3 OnboardingController — Core Pattern

```php
// app/Http/Controllers/OnboardingController.php

class OnboardingController extends Controller
{
    // Entry point — validates signed URL, creates session
    public function start(Request $request, int $telegram_id): RedirectResponse
    {
        // Signature already validated by 'signed' middleware
        $user = User::where('telegram_chat_id', $telegram_id)->firstOrFail();

        if ($user->onboarding_complete_at) {
            return redirect()->route('dashboard.index');
        }

        // Mark token as used — prevent replay
        // Simple: check if onboarding_step already passed 'pending'
        if ($user->onboarding_step !== 'pending') {
            return view('onboarding.expired'); // "Link sudah digunakan"
        }

        // Store identity in session
        session(['onboarding_user_id' => $user->id]);

        return redirect()->route('onboarding.profile', $telegram_id);
    }

    // Every subsequent page resolves user from session
    private function getOnboardingUser(): User
    {
        $userId = session('onboarding_user_id');
        abort_if(!$userId, 403, 'Session expired. Please request a new setup link.');
        return User::findOrFail($userId);
    }

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

    public function saveAccounts(Request $request, int $telegram_id): RedirectResponse
    {
        $user = $this->getOnboardingUser();

        $request->validate([
            'accounts'           => 'required|array|min:1',
            'accounts.*.name'    => 'required|string|max:128',
            'accounts.*.type'    => 'required|in:bank,ewallet,cash,other',
            'accounts.*.balance' => 'nullable|integer|min:0',
            'default_account'    => 'required|integer',   // index of default in accounts array
        ]);

        foreach ($request->accounts as $index => $accountData) {
            $account = Account::create([
                'user_id'         => $user->id,
                'name'            => $accountData['name'],
                'type'            => $accountData['type'],
                'current_balance' => $accountData['balance'] ?? 0,
                'is_default'      => ($index === (int) $request->default_account),
            ]);

            if ($account->is_default) {
                $user->update(['default_account_id' => $account->id]);
            }
        }

        $user->update(['onboarding_step' => 'accounts_done']);

        return redirect()->route('onboarding.budget', $telegram_id);
    }

    public function done(int $telegram_id): View
    {
        $user = $this->getOnboardingUser();

        $user->update([
            'onboarding_step'        => 'complete',
            'onboarding_complete_at' => now(),
        ]);

        // Notify user in Telegram that setup is complete
        SendOnboardingCompleteNotification::dispatch($user);

        session()->forget('onboarding_user_id');

        return view('onboarding.done', [
            'user'        => $user,
            'bot_username' => config('butler.telegram_bot_username'),
        ]);
    }
}
```

### 1.4 Expired / Already-Used Link Handling

```php
// In start() — if token already used:
if ($user->onboarding_step !== 'pending') {
    return view('onboarding.expired');
}
```

```blade
{{-- resources/views/onboarding/expired.blade.php --}}
<div>
    <h1>Link sudah tidak berlaku.</h1>
    <p>Kirim /start lagi di Telegram untuk mendapatkan link baru.</p>
    <a href="tg://resolve?domain={{ config('butler.telegram_bot_username') }}">
        Buka Telegram
    </a>
</div>
```

If the session expires mid-onboarding (user closes browser, comes back):

```php
// getOnboardingUser() will abort(403)
// Blade error page tells user to request a new link from Telegram
```

Alternatively, store a `setup_session_token` in the user record
and use that as a session-independent identity for the setup flow.
Simpler for debugging.

---

## Part 2 — Onboarding Pages (Blade + Alpine.js)

Minimal functional UI. No design framework required.
Each page is a form POST with a next button.

### Layout

```blade
{{-- resources/views/layouts/onboarding.blade.php --}}
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Butler Setup</title>
    <script defer src="https://unpkg.com/alpinejs@3.x.x/dist/cdn.min.js"></script>
    <style>
        /* Minimal functional styles */
        body { font-family: system-ui, sans-serif; max-width: 480px; margin: 0 auto; padding: 24px 16px; }
        input, select { width: 100%; padding: 10px; border: 1px solid #ccc; border-radius: 8px; font-size: 16px; box-sizing: border-box; margin-top: 6px; }
        button { width: 100%; padding: 14px; background: #000; color: #fff; border: none; border-radius: 8px; font-size: 16px; cursor: pointer; margin-top: 16px; }
        .skip { background: transparent; color: #666; border: 1px solid #ccc; margin-top: 8px; }
        .step { font-size: 13px; color: #999; margin-bottom: 24px; }
        label { font-size: 14px; color: #333; font-weight: 500; }
        .error { color: red; font-size: 13px; margin-top: 4px; }
        .chip { display: inline-block; padding: 8px 14px; border: 1px solid #ccc; border-radius: 20px; margin: 4px; cursor: pointer; font-size: 14px; }
        .chip.selected { background: #000; color: #fff; border-color: #000; }
    </style>
</head>
<body>
    @yield('content')
</body>
</html>
```

### Step 1 — Profile

```blade
{{-- resources/views/onboarding/profile.blade.php --}}
@extends('layouts.onboarding')
@section('content')
<div class="step">Langkah 1 dari 5</div>

<h2>Hai! Siapa nama kamu?</h2>
<p style="color:#666;font-size:14px">Butler akan menggunakannya untuk menyapa kamu.</p>

<form method="POST" action="{{ route('onboarding.profile.save', $telegram_id) }}">
    @csrf

    <label>Nama / Panggilan
        <input type="text" name="name" value="{{ old('name', $user->name) }}"
               placeholder="contoh: Andi" autofocus required>
        @error('name') <div class="error">{{ $message }}</div> @enderror
    </label>

    <label style="margin-top:16px">Mata uang
        <select name="currency">
            <option value="IDR" selected>IDR — Rupiah</option>
            <option value="USD">USD — US Dollar</option>
            <option value="SGD">SGD — Singapore Dollar</option>
        </select>
    </label>

    <label style="margin-top:16px">Zona waktu
        <select name="timezone">
            <option value="Asia/Jakarta" selected>WIB — Jakarta (UTC+7)</option>
            <option value="Asia/Makassar">WITA — Makassar (UTC+8)</option>
            <option value="Asia/Jayapura">WIT — Jayapura (UTC+9)</option>
        </select>
    </label>

    <button type="submit">Lanjut →</button>
</form>
@endsection
```

### Step 2 — Accounts

```blade
{{-- resources/views/onboarding/accounts.blade.php --}}
@extends('layouts.onboarding')
@section('content')
<div class="step">Langkah 2 dari 5</div>

<h2>Di mana kamu simpan uang?</h2>
<p style="color:#666;font-size:14px">
    Tambahkan akun yang sering kamu pakai. Saldo awal boleh dikosongkan.
</p>

<div x-data="accountManager()" x-init="init()">

    {{-- Suggestions --}}
    <div style="margin-bottom:16px">
        <div style="font-size:13px;color:#999;margin-bottom:8px">Pilih cepat:</div>
        <template x-for="s in suggestions" :key="s.name">
            <span class="chip" :class="{ selected: isAdded(s.name) }"
                  @click="addSuggestion(s)" x-text="s.name"></span>
        </template>
    </div>

    {{-- Account list --}}
    <template x-for="(account, index) in accounts" :key="index">
        <div style="border:1px solid #eee;border-radius:8px;padding:12px;margin-bottom:8px">
            <div style="display:flex;justify-content:space-between;align-items:center">
                <strong x-text="account.name"></strong>
                <button type="button" @click="remove(index)"
                        style="background:none;border:none;color:#999;cursor:pointer;width:auto;padding:4px">✕</button>
            </div>
            <div style="font-size:13px;color:#666;margin-top:4px" x-text="account.type_label"></div>
            <input type="number" placeholder="Saldo awal (opsional)"
                   x-model="account.balance" style="margin-top:8px">
            <label style="margin-top:8px;display:flex;align-items:center;gap:8px;font-size:14px">
                <input type="radio" name="default_account" :value="index"
                       x-model="defaultIndex"> Akun utama (untuk pengeluaran harian)
            </label>
        </div>
    </template>

    {{-- Add custom --}}
    <div x-show="showCustomForm" style="border:1px solid #eee;border-radius:8px;padding:12px;margin-bottom:8px">
        <input type="text" x-model="customName" placeholder="Nama akun (contoh: Jenius)">
        <select x-model="customType" style="margin-top:8px">
            <option value="bank">Bank</option>
            <option value="ewallet">E-wallet</option>
            <option value="cash">Cash</option>
            <option value="other">Lainnya</option>
        </select>
        <button type="button" @click="addCustom()" style="margin-top:8px">Tambah</button>
        <button type="button" @click="showCustomForm=false" class="skip">Batal</button>
    </div>

    <button type="button" @click="showCustomForm=true" class="skip" x-show="!showCustomForm">
        + Tambah akun lain
    </button>

    {{-- Hidden form for submission --}}
    <form method="POST" action="{{ route('onboarding.accounts.save', $telegram_id) }}" id="accountsForm">
        @csrf
        <template x-for="(account, index) in accounts" :key="index">
            <div>
                <input type="hidden" :name="'accounts[' + index + '][name]'" :value="account.name">
                <input type="hidden" :name="'accounts[' + index + '][type]'" :value="account.type">
                <input type="hidden" :name="'accounts[' + index + '][balance]'" :value="account.balance || 0">
            </div>
        </template>
        <input type="hidden" name="default_account" :value="defaultIndex" x-model="defaultIndex">
        <button type="submit" @click.prevent="submitForm()" :disabled="accounts.length === 0">
            Lanjut →
        </button>
    </form>
</div>

<script>
function accountManager() {
    return {
        accounts: [],
        defaultIndex: 0,
        showCustomForm: false,
        customName: '',
        customType: 'bank',
        suggestions: [
            { name: 'BCA',     type: 'bank',    type_label: 'Bank' },
            { name: 'Mandiri', type: 'bank',    type_label: 'Bank' },
            { name: 'BRI',     type: 'bank',    type_label: 'Bank' },
            { name: 'GoPay',   type: 'ewallet', type_label: 'E-wallet' },
            { name: 'OVO',     type: 'ewallet', type_label: 'E-wallet' },
            { name: 'Dana',    type: 'ewallet', type_label: 'E-wallet' },
            { name: 'Cash',    type: 'cash',    type_label: 'Cash' },
        ],
        init() {},
        isAdded(name) { return this.accounts.some(a => a.name === name); },
        addSuggestion(s) {
            if (this.isAdded(s.name)) {
                this.accounts = this.accounts.filter(a => a.name !== s.name);
            } else {
                this.accounts.push({ name: s.name, type: s.type, type_label: s.type_label, balance: '' });
            }
        },
        addCustom() {
            if (!this.customName.trim()) return;
            const labels = { bank: 'Bank', ewallet: 'E-wallet', cash: 'Cash', other: 'Lainnya' };
            this.accounts.push({ name: this.customName, type: this.customType, type_label: labels[this.customType], balance: '' });
            this.customName = '';
            this.showCustomForm = false;
        },
        remove(index) {
            this.accounts.splice(index, 1);
            if (this.defaultIndex >= this.accounts.length) this.defaultIndex = 0;
        },
        submitForm() {
            if (this.accounts.length === 0) return;
            document.getElementById('accountsForm').submit();
        }
    }
}
</script>
@endsection
```

### Step 3 — Budget

```blade
{{-- resources/views/onboarding/budget.blade.php --}}
@extends('layouts.onboarding')
@section('content')
<div class="step">Langkah 3 dari 5</div>

<h2>Target pengeluaran bulanan?</h2>
<p style="color:#666;font-size:14px">
    Ini opsional. Butler pakai ini buat alert kalau kamu mendekati limit.
</p>

<form method="POST" action="{{ route('onboarding.budget.save', $telegram_id) }}">
    @csrf
    <label>Budget bulanan (IDR)
        <input type="number" name="monthly_budget" placeholder="contoh: 3000000"
               value="{{ old('monthly_budget') }}" min="0">
    </label>
    <button type="submit">Lanjut →</button>
    <button type="submit" name="skip" value="1" class="skip">Lewati</button>
</form>
@endsection
```

### Step 4 — Health

```blade
{{-- resources/views/onboarding/health.blade.php --}}
@extends('layouts.onboarding')
@section('content')
<div class="step">Langkah 4 dari 5</div>

<h2>Mau track kalori juga?</h2>
<p style="color:#666;font-size:14px">
    Butler bisa estimasi kalori dari makanan yang kamu catat.
</p>

<form method="POST" action="{{ route('onboarding.health.save', $telegram_id) }}"
      x-data="{ track: false }">
    @csrf

    <label style="display:flex;align-items:center;gap:12px;padding:16px;border:1px solid #eee;border-radius:8px;cursor:pointer">
        <input type="checkbox" name="calorie_tracking" value="1" x-model="track">
        <span>Ya, aktifkan calorie tracking</span>
    </label>

    <div x-show="track" style="margin-top:16px">
        <label>Target kalori harian
            <input type="number" name="calorie_goal" placeholder="contoh: 2200" min="500" max="5000">
        </label>

        <label style="margin-top:16px">Tujuan kesehatan
            <select name="health_goal">
                <option value="">Pilih (opsional)</option>
                <option value="maintain">Maintain berat badan</option>
                <option value="lose">Turunkan berat badan</option>
                <option value="gain">Naikkan berat badan</option>
                <option value="protein">Tambah asupan protein</option>
            </select>
        </label>
    </div>

    <button type="submit">Lanjut →</button>
    <button type="submit" name="skip" value="1" class="skip">Lewati</button>
</form>
@endsection
```

### Step 5 — Notifications

```blade
{{-- resources/views/onboarding/notifications.blade.php --}}
@extends('layouts.onboarding')
@section('content')
<div class="step">Langkah 5 dari 5</div>

<h2>Ringkasan harian?</h2>
<p style="color:#666;font-size:14px">
    Butler kirim ringkasan pengeluaran dan kalori harian ke Telegram kamu.
</p>

<form method="POST" action="{{ route('onboarding.notifications.save', $telegram_id) }}"
      x-data="{ enabled: true }">
    @csrf

    <label style="display:flex;align-items:center;gap:12px;padding:16px;border:1px solid #eee;border-radius:8px;cursor:pointer">
        <input type="checkbox" name="daily_summary" value="1" x-model="enabled" checked>
        <span>Aktifkan ringkasan harian</span>
    </label>

    <div x-show="enabled" style="margin-top:16px">
        <label>Jam berapa?
            <input type="time" name="summary_time" value="21:00">
        </label>
    </div>

    <button type="submit">Selesai →</button>
</form>
@endsection
```

### Done Page

```blade
{{-- resources/views/onboarding/done.blade.php --}}
@extends('layouts.onboarding')
@section('content')
<div style="text-align:center;padding-top:48px">
    <div style="font-size:48px">✅</div>
    <h2>Siap digunakan!</h2>
    <p style="color:#666">
        Butler sudah tahu tentang kamu.<br>
        Mulai catat di Telegram sekarang.
    </p>

    <div style="background:#f5f5f5;border-radius:8px;padding:16px;text-align:left;margin:24px 0;font-size:14px">
        <p style="margin:0 0 8px;color:#999;font-size:12px">CONTOH PERINTAH</p>
        <p style="margin:4px 0">• "makan ayam geprek 35k"</p>
        <p style="margin:4px 0">• "grab 18rb"</p>
        <p style="margin:4px 0">• "gajian 5 juta"</p>
        <p style="margin:4px 0">• "rangkuman hari ini"</p>
    </div>

    <a href="tg://resolve?domain={{ $bot_username }}"
       style="display:block;background:#000;color:#fff;padding:14px;border-radius:8px;text-decoration:none;font-size:16px">
        Buka Telegram
    </a>
</div>
@endsection
```

---

## Part 3 — Telegram ↔ Webview Completion Handoff

When the user completes onboarding in the webview, Butler sends a message in Telegram:

```php
// app/Jobs/SendOnboardingCompleteNotification.php

class SendOnboardingCompleteNotification implements ShouldQueue
{
    public function __construct(private User $user) {}

    public function handle(TelegramService $telegram): void
    {
        $accounts = $this->user->accounts()->get();
        $accountList = $accounts->map(fn($a) => "• {$a->name}")->join("\n");

        $telegram->sendMessage([
            'chat_id' => $this->user->telegram_chat_id,
            'text'    => implode("\n", [
                "Setup selesai, {$this->user->name}! 🎉",
                "",
                "Akun terdaftar:",
                $accountList,
                "",
                "Coba catat sesuatu sekarang.",
            ]),
        ]);
    }
}
```

---

## Part 4 — Dashboard Auth (Persistent Access)

### How User Accesses Dashboard

User types "buka dashboard" or "/dashboard" in Telegram.
Bot generates a fresh signed link valid for 30 minutes.

```php
// In TelegramWebhookController — handle dashboard intent

private function handleDashboardRequest(User $user): void
{
    $url = URL::temporarySignedRoute(
        'dashboard.auth',
        now()->addMinutes(30),
        ['telegram_id' => $user->telegram_chat_id]
    );

    $this->telegram->sendMessage([
        'chat_id'      => $user->telegram_chat_id,
        'text'         => 'Link aktif selama 30 menit.',
        'reply_markup' => json_encode([
            'inline_keyboard' => [[
                ['text' => '📊 Buka Dashboard', 'url' => $url]
            ]]
        ])
    ]);
}
```

### Dashboard Auth Route

```php
// routes/web.php

Route::get('/dashboard/auth/{telegram_id}', [DashboardController::class, 'auth'])
    ->name('dashboard.auth')
    ->middleware('signed');

Route::middleware('dashboard.session')->group(function () {
    Route::get('/dashboard',                    [DashboardController::class, 'index'])->name('dashboard.index');
    Route::get('/dashboard/accounts',           [DashboardController::class, 'accounts']);
    Route::get('/dashboard/bills',              [DashboardController::class, 'bills']);
    Route::get('/dashboard/funds',              [DashboardController::class, 'funds']);
    Route::get('/dashboard/history',            [DashboardController::class, 'history']);
    Route::get('/dashboard/settings',           [DashboardController::class, 'settings']);
    // POST routes for each section
});
```

### Dashboard Auth Controller

```php
// app/Http/Controllers/DashboardController.php

public function auth(Request $request, int $telegram_id): RedirectResponse
{
    // Signature validated by 'signed' middleware
    $user = User::where('telegram_chat_id', $telegram_id)
                ->whereNotNull('onboarding_complete_at')
                ->firstOrFail();

    // Store in session — 30-minute session lifetime
    session([
        'dashboard_user_id'  => $user->id,
        'dashboard_expires'  => now()->addMinutes(30)->timestamp,
    ]);

    return redirect()->route('dashboard.index');
}
```

### Dashboard Session Middleware

```php
// app/Http/Middleware/DashboardSession.php

class DashboardSession
{
    public function handle(Request $request, Closure $next): Response
    {
        $userId  = session('dashboard_user_id');
        $expires = session('dashboard_expires');

        if (!$userId || !$expires || now()->timestamp > $expires) {
            // Session expired — show message to re-request link from Telegram
            return response()->view('dashboard.expired', [], 403);
        }

        // Refresh expiry on activity (sliding session)
        session(['dashboard_expires' => now()->addMinutes(30)->timestamp]);

        // Make user available to controllers
        $request->merge(['dashboard_user' => User::findOrFail($userId)]);

        return $next($request);
    }
}
```

Register in `app/Http/Kernel.php`:

```php
protected $middlewareAliases = [
    // ...
    'dashboard.session' => \App\Http\Middleware\DashboardSession::class,
];
```

---

## Part 5 — Dashboard Pages (Functional)

Minimal functional pages. Read + edit only where needed for demo.

### Dashboard Index

```blade
{{-- resources/views/dashboard/index.blade.php --}}
@extends('layouts.dashboard')
@section('content')
<h2>Halo, {{ $user->name }} 👋</h2>

<div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-top:16px">
    <a href="/dashboard/accounts" class="card">💳 Akun</a>
    <a href="/dashboard/history"  class="card">📋 Riwayat</a>
    <a href="/dashboard/bills"    class="card">🏠 Tagihan</a>
    <a href="/dashboard/funds"    class="card">🎯 Dana</a>
    <a href="/dashboard/settings" class="card" style="grid-column:span 2">⚙️ Pengaturan</a>
</div>
@endsection
```

### Dashboard — History (Most Important for Demo)

```blade
{{-- resources/views/dashboard/history.blade.php --}}
@extends('layouts.dashboard')
@section('content')
<h2>Riwayat Transaksi</h2>

{{-- Filters --}}
<form method="GET" style="display:flex;gap:8px;margin-bottom:16px;flex-wrap:wrap">
    <select name="type" onchange="this.form.submit()">
        <option value="">Semua tipe</option>
        <option value="expense" {{ request('type')=='expense'?'selected':'' }}>Pengeluaran</option>
        <option value="meal"    {{ request('type')=='meal'?'selected':'' }}>Makanan</option>
        <option value="income"  {{ request('type')=='income'?'selected':'' }}>Pemasukan</option>
    </select>
    <input type="date" name="from" value="{{ request('from') }}" onchange="this.form.submit()">
    <input type="date" name="to"   value="{{ request('to') }}"   onchange="this.form.submit()">
</form>

{{-- Table --}}
<table style="width:100%;border-collapse:collapse;font-size:14px">
    <thead>
        <tr style="border-bottom:2px solid #eee;text-align:left">
            <th style="padding:8px">Waktu</th>
            <th style="padding:8px">Keterangan</th>
            <th style="padding:8px;text-align:right">Jumlah</th>
            <th style="padding:8px">Akun</th>
            <th style="padding:8px"></th>
        </tr>
    </thead>
    <tbody>
        @forelse($entries as $entry)
        <tr style="border-bottom:1px solid #f0f0f0">
            <td style="padding:8px;color:#999;font-size:12px">
                {{ $entry->entry_time->format('d M H:i') }}
            </td>
            <td style="padding:8px">
                {{ $entry->merchant ?? $entry->food_item ?? $entry->note ?? '—' }}
                @if($entry->category)
                    <span style="font-size:11px;color:#999"> · {{ $entry->category }}</span>
                @endif
            </td>
            <td style="padding:8px;text-align:right;font-weight:500">
                Rp{{ number_format($entry->amount, 0, ',', '.') }}
            </td>
            <td style="padding:8px;color:#666;font-size:13px">
                {{ $entry->account->name ?? '—' }}
            </td>
            <td style="padding:8px">
                <button onclick="softDelete({{ $entry->id }})"
                        style="background:none;border:none;color:#ccc;cursor:pointer;font-size:16px">🗑</button>
            </td>
        </tr>
        @empty
        <tr><td colspan="5" style="padding:24px;text-align:center;color:#999">Belum ada transaksi.</td></tr>
        @endforelse
    </tbody>
</table>

{{ $entries->links() }}
@endsection
```

---

## Part 6 — Config & Environment

```php
// config/butler.php

return [
    'telegram_bot_username' => env('TELEGRAM_BOT_USERNAME'),
    'telegram_bot_token'    => env('TELEGRAM_BOT_TOKEN'),
    'webhook_url'           => env('APP_URL') . '/api/telegram/webhook',
    'onboarding_link_ttl'   => 10,   // minutes
    'dashboard_link_ttl'    => 30,   // minutes
    'undo_window_minutes'   => 5,
    'behavioral_auto_apply_threshold' => 0.80,
    'behavioral_consent_threshold'    => 0.80,
];
```

```env
TELEGRAM_BOT_TOKEN=your_token_here
TELEGRAM_BOT_USERNAME=ButlerAI_bot
APP_URL=https://your-domain.com
APP_KEY=base64:...        # required for signed URL integrity
```

**Critical:** `APP_KEY` must be set and stable in production.
Signed URLs are HMAC-signed with this key. If it changes, all outstanding
links (including mid-onboarding ones) immediately break.

---

## Part 7 — Onboarding State Reference

| Step value | Set when | Next step |
|---|---|---|
| `pending` | User record created on /start | URL clicked → profile |
| `profile_done` | Profile form submitted | accounts |
| `accounts_done` | Accounts saved | budget |
| `budget_done` | Budget saved (or skipped) | health |
| `health_done` | Health saved (or skipped) | notifications |
| `notifications_done` | Notifications saved | done |
| `complete` | Done page rendered | — |

If user abandons mid-onboarding and sends `/start` again:

```php
// If onboarding_step != 'pending' AND != 'complete'
// → Resend a new signed link, it continues from current step
// → Route::get('/setup/{token}') redirects to the step they're on
```

---

## Part 8 — Route Summary

```
GET  /setup/{telegram_id}                  → signed, onboarding entry
GET  /setup/{telegram_id}/profile          → profile form
POST /setup/{telegram_id}/profile          → save profile
GET  /setup/{telegram_id}/accounts         → accounts form
POST /setup/{telegram_id}/accounts         → save accounts
GET  /setup/{telegram_id}/budget           → budget form
POST /setup/{telegram_id}/budget           → save budget
GET  /setup/{telegram_id}/health           → health form
POST /setup/{telegram_id}/health           → save health
GET  /setup/{telegram_id}/notifications    → notifications form
POST /setup/{telegram_id}/notifications    → save notifications
GET  /setup/{telegram_id}/done             → completion page

GET  /dashboard/auth/{telegram_id}         → signed, sets session
GET  /dashboard                            → index (session protected)
GET  /dashboard/accounts                   → accounts list + edit
GET  /dashboard/bills                      → bills management
GET  /dashboard/funds                      → sinking funds + goals
GET  /dashboard/history                    → transaction log
GET  /dashboard/settings                   → all preferences
```

---

*Last updated: May 2026 — Webview Implementation v1.0*
