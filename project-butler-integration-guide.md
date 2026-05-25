# Project Butler — Integration Guide
## Telegram ↔ Webview Connection & Mismatch Resolution

> This document identifies every point where Telegram and the webview
> must be in sync, resolves mismatches between v2.1 and the webview
> implementation, and defines the exact connection logic for each touchpoint.

---

## Identified Mismatches

| # | Mismatch | v2.1 says | Webview says | Resolution |
|---|---|---|---|---|
| 1 | Onboarding gate | Users go straight to webview | No gate in Telegram for mid-onboarding users | Add state guard in Telegram webhook |
| 2 | Behavioral memory init | Learns from usage | Never initialized after onboarding | Seed behavioral_memory from webview outputs |
| 3 | Default account propagation | Resolve layer uses `default_account_id` | Set in webview but never confirmed back to Telegram | Add confirmation in completion job |
| 4 | Summary scheduler | Reads `summary_time` per user | Set in webview notifications step | Must write to `users.summary_time` column |
| 5 | `onboarding_step` | Referenced in v2.1 | Different step names in webview impl | Unify into one canonical state machine |
| 6 | Post-onboarding first message | Undefined | Undefined | Define exact first-interaction flow |
| 7 | Dashboard intent | "buka dashboard" sends signed URL | Separate from webhook logic | Wire intent into webhook parser |
| 8 | Mid-session Telegram logging | Full pipeline assumed ready | Accounts may not exist yet | Gate all logging behind `onboarding_complete` |
| 9 | Undo + webview edit conflict | Undo in Telegram | Edit in dashboard | Define which wins and when |
| 10 | `users` table columns | Several columns implied | Several columns implied differently | Canonical schema defined below |

---

## Canonical `users` Table (Unified)

This is the single source of truth. Both Telegram and webview read and write here.

```sql
CREATE TABLE users (
  id                        BIGINT PRIMARY KEY AUTO_INCREMENT,
  telegram_chat_id          BIGINT UNIQUE NOT NULL,
  telegram_username         VARCHAR(64) NULL,

  -- Identity (set in webview /profile)
  name                      VARCHAR(128) NULL,
  currency                  CHAR(3) DEFAULT 'IDR',
  timezone                  VARCHAR(64) DEFAULT 'Asia/Jakarta',
  preferred_language        ENUM('id','en') DEFAULT 'id',

  -- Tracking mode (set in webview /health)
  tracking_mode             ENUM('finance','calorie','both') DEFAULT 'finance',

  -- Budgets (set in webview /budget and /health)
  monthly_budget_idr        INTEGER NULL,
  daily_calorie_goal        INTEGER NULL,
  health_goal               ENUM('maintain','lose','gain','protein') NULL,

  -- Default account (set in webview /accounts)
  default_account_id        BIGINT NULL REFERENCES accounts(id),

  -- Notifications (set in webview /notifications)
  daily_summary_enabled     BOOLEAN DEFAULT TRUE,
  summary_time              TIME DEFAULT '21:00:00',   -- stored in user's timezone

  -- Onboarding state (shared between Telegram + webview)
  onboarding_step           ENUM(
                              'new',              -- user record just created
                              'link_sent',        -- /start sent, webview link not opened yet
                              'webview_opened',   -- user clicked link, session started
                              'profile_done',
                              'accounts_done',
                              'budget_done',
                              'health_done',
                              'notifications_done',
                              'complete'
                            ) DEFAULT 'new',
  onboarding_complete_at    TIMESTAMP NULL,
  onboarding_started_at     TIMESTAMP NULL,       -- when webview was first opened

  -- Setup completeness (drives setup reminders after onboarding)
  has_bills_setup           BOOLEAN DEFAULT FALSE,
  has_income_set            BOOLEAN DEFAULT FALSE,
  has_debt_declared         BOOLEAN DEFAULT FALSE,

  -- Activity
  last_active_at            TIMESTAMP NULL,
  last_summary_sent_at      TIMESTAMP NULL,

  created_at                TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at                TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);
```

---

## The Unified State Machine

One state machine. Both Telegram and webview write to it. Both read from it.

```
                        USER SENDS /start
                               │
                    ┌──────────▼──────────┐
                    │  Telegram Webhook   │
                    │  handleStart()      │
                    └──────────┬──────────┘
                               │
              ┌────────────────┼────────────────┐
              │                │                │
        state=new        state=link_sent    state=complete
              │          (resend link)           │
              ▼                                  ▼
    Create user record                   Send welcome back
    Generate signed URL                  + usage examples
    Send [Mulai Setup]
    state → link_sent
              │
              ▼
    USER CLICKS LINK IN TELEGRAM
              │
    ┌─────────▼─────────┐
    │  /setup/{token}   │  signed middleware validates
    │  auth() method    │  state → webview_opened
    └─────────┬─────────┘  session created
              │
    ┌─────────▼──────────────────────────────────┐
    │  WEBVIEW STEPS (each POST updates state)   │
    │  /profile        → state: profile_done     │
    │  /accounts       → state: accounts_done    │
    │  /budget         → state: budget_done      │
    │  /health         → state: health_done      │
    │  /notifications  → state: notifications_done│
    │  /done           → state: complete         │
    │                    + seeds behavioral_memory│
    │                    + dispatches completion job│
    └─────────┬──────────────────────────────────┘
              │
    ┌─────────▼─────────┐
    │ SendOnboarding    │  ← runs as queued job
    │ CompleteJob       │    after /done renders
    └─────────┬─────────┘
              │
    TELEGRAM RECEIVES:
    "Setup selesai, {name}!
     Akun: GoPay (utama), BCA
     Coba ketik sesuatu sekarang."
              │
              ▼
    ALL FUTURE TELEGRAM MESSAGES
    → routed through full 4-layer pipeline
```

---

## Touchpoint 1 — `/start` Handler (Telegram Side)

This is the entry point. It must handle all possible `onboarding_step` states.

```php
// app/Http/Controllers/TelegramWebhookController.php

private function handleStart(array $from): void
{
    $chatId   = $from['id'];
    $username = $from['username'] ?? null;

    $user = User::firstOrCreate(
        ['telegram_chat_id' => $chatId],
        [
            'telegram_username' => $username,
            'onboarding_step'   => 'new',
        ]
    );

    // Update username if it changed
    if ($username && $user->telegram_username !== $username) {
        $user->update(['telegram_username' => $username]);
    }

    match (true) {
        // Fully onboarded
        $user->onboarding_step === 'complete' => $this->sendWelcomeBack($user),

        // Mid-onboarding — resend a fresh link
        in_array($user->onboarding_step, [
            'link_sent', 'webview_opened',
            'profile_done', 'accounts_done',
            'budget_done', 'health_done',
        ]) => $this->resendOnboardingLink($user),

        // New or reset
        default => $this->sendOnboardingLink($user),
    };
}

private function sendOnboardingLink(User $user): void
{
    $url = URL::temporarySignedRoute(
        'onboarding.start',
        now()->addMinutes(10),
        ['telegram_id' => $user->telegram_chat_id]
    );

    $user->update(['onboarding_step' => 'link_sent']);

    $this->telegram->sendMessage([
        'chat_id'      => $user->telegram_chat_id,
        'text'         => "Halo! Aku Butler, asisten keuangan harianmu.\nSetup butuh sekitar 3 menit.",
        'reply_markup' => json_encode([
            'inline_keyboard' => [[
                ['text' => '🚀 Mulai Setup', 'url' => $url]
            ]]
        ])
    ]);
}

private function resendOnboardingLink(User $user): void
{
    $url = URL::temporarySignedRoute(
        'onboarding.start',
        now()->addMinutes(10),
        ['telegram_id' => $user->telegram_chat_id]
    );

    $stepLabels = [
        'link_sent'     => 'belum dimulai',
        'webview_opened'=> 'baru dibuka',
        'profile_done'  => 'di langkah akun',
        'accounts_done' => 'di langkah budget',
        'budget_done'   => 'di langkah kesehatan',
        'health_done'   => 'hampir selesai',
    ];

    $progress = $stepLabels[$user->onboarding_step] ?? '';

    $this->telegram->sendMessage([
        'chat_id'      => $user->telegram_chat_id,
        'text'         => "Setup kamu {$progress}. Lanjutin dari sini:",
        'reply_markup' => json_encode([
            'inline_keyboard' => [[
                ['text' => '↩ Lanjut Setup', 'url' => $url]
            ]]
        ])
    ]);
}

private function sendWelcomeBack(User $user): void
{
    $account = $user->defaultAccount;

    $this->telegram->sendMessage([
        'chat_id' => $user->telegram_chat_id,
        'text'    => implode("\n", [
            "Halo lagi, {$user->name}!",
            "",
            "Akun aktif: {$account?->name}",
            "",
            "Contoh:",
            "• makan ayam geprek 35k",
            "• grab 18rb",
            "• rangkuman hari ini",
        ]),
    ]);
}
```

---

## Touchpoint 2 — Webview Entry (Onboarding Gate)

```php
// app/Http/Controllers/OnboardingController.php

public function start(Request $request, int $telegram_id): Response
{
    // 'signed' middleware already validated URL integrity + expiry

    $user = User::where('telegram_chat_id', $telegram_id)->firstOrFail();

    // Already complete — redirect to dashboard
    if ($user->onboarding_step === 'complete') {
        return redirect()->route('dashboard.index');
    }

    // Mark as opened + record timestamp
    $user->update([
        'onboarding_step'     => 'webview_opened',
        'onboarding_started_at' => $user->onboarding_started_at ?? now(),
    ]);

    // Store user identity in session
    session([
        'onboarding_user_id' => $user->id,
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
```

---

## Touchpoint 3 — Accounts Step (Critical Connection)

This step sets `default_account_id` on the user — which feeds the resolve layer
in the 4-layer pipeline. Must complete before any logging works.

```php
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

    $defaultIndex = (int) $validated['default_account_index'];
    $defaultAccountId = null;

    foreach ($validated['accounts'] as $index => $accountData) {
        $isDefault = ($index === $defaultIndex);

        $account = Account::create([
            'user_id'         => $user->id,
            'name'            => $accountData['name'],
            'type'            => $accountData['type'],
            'current_balance' => $accountData['balance'] ?? 0,
            'is_default'      => $isDefault,
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
```

---

## Touchpoint 4 — Notifications Step (Feeds Scheduler)

The `summary_time` set here is what the daily summary scheduler reads.
Must be written to `users.summary_time` in the user's own timezone.

```php
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
        'summary_time'          => $validated['summary_time'] ?? '21:00:00',
        'onboarding_step'       => 'notifications_done',
    ]);

    return redirect()->route('onboarding.done', $telegram_id);
}
```

The scheduler reads this correctly:

```php
// app/Console/Kernel.php

protected function schedule(Schedule $schedule): void
{
    // Run every minute, let the job filter by summary_time
    $schedule->job(new DispatchDailySummaries)->everyMinute();
}
```

```php
// app/Jobs/DispatchDailySummaries.php

public function handle(): void
{
    $now = now(); // UTC

    User::where('onboarding_step', 'complete')
        ->where('daily_summary_enabled', true)
        ->whereNull('last_summary_sent_at')  // or sent before today
        ->orWhereDate('last_summary_sent_at', '<', today())
        ->get()
        ->each(function (User $user) use ($now) {
            // Convert user's summary_time to UTC for comparison
            $userNow  = $now->copy()->setTimezone($user->timezone);
            $sendAt   = Carbon::parse($user->summary_time, $user->timezone);

            // Match within the current minute
            if ($userNow->format('H:i') === $sendAt->format('H:i')) {
                SendDailySummary::dispatch($user)->onQueue('low');
            }
        });
}
```

---

## Touchpoint 5 — Completion & Behavioral Memory Seeding

When `/done` renders, two things must happen:
1. Behavioral memory is seeded with reasonable defaults from what was set up
2. Telegram is notified

```php
public function done(int $telegram_id): View
{
    $user = $this->getOnboardingUser();

    // Finalize onboarding
    $user->update([
        'onboarding_step'        => 'complete',
        'onboarding_complete_at' => now(),
    ]);

    // Seed behavioral memory from onboarding data
    SeedBehavioralMemoryFromOnboarding::dispatch($user)->onQueue('low');

    // Notify user in Telegram
    SendOnboardingCompleteNotification::dispatch($user)->onQueue('default');

    // Clear session
    session()->forget(['onboarding_user_id', 'onboarding_telegram_id']);

    return view('onboarding.done', [
        'user'         => $user,
        'bot_username' => config('butler.telegram_bot_username'),
    ]);
}
```

```php
// app/Jobs/SeedBehavioralMemoryFromOnboarding.php

public function handle(): void
{
    $user     = $this->user;
    $accounts = $user->accounts()->get();
    $default  = $user->defaultAccount;

    if (!$default) return;

    // Seed category → default account as baseline learned preferences
    // These start with low confidence — will strengthen through usage
    $categoryDefaults = ['food_drink', 'transport', 'shopping', 'entertainment', 'other'];

    foreach ($categoryDefaults as $category) {
        BehavioralMemory::updateOrCreate(
            [
                'user_id'  => $user->id,
                'domain'   => 'category_account',
                'subject'  => $category,
            ],
            [
                'value'                  => [
                    'account_id'   => $default->id,
                    'account_name' => $default->name,
                ],
                'behavioral_confidence'  => 0.10,  // low — just a starting point
                'observation_count'      => 0,
                'user_consented'         => false,
                'auto_apply'             => false,
                'last_observed_at'       => now(),
            ]
        );
    }

    // If user has e-wallet as default, seed it as preferred for food/transport
    if ($default->type === 'ewallet') {
        foreach (['food_drink', 'transport'] as $cat) {
            BehavioralMemory::where([
                'user_id' => $user->id,
                'domain'  => 'category_account',
                'subject' => $cat,
            ])->update(['behavioral_confidence' => 0.20]); // slightly higher for common e-wallet use
        }
    }
}
```

```php
// app/Jobs/SendOnboardingCompleteNotification.php

public function handle(TelegramService $telegram): void
{
    $user    = $this->user;
    $default = $user->defaultAccount;
    $others  = $user->accounts()->where('is_default', false)->pluck('name');

    $accountLine = $default?->name ?? 'belum diset';
    if ($others->isNotEmpty()) {
        $accountLine .= ' + ' . $others->join(', ');
    }

    $calorieLine = $user->daily_calorie_goal
        ? "Target kalori: {$user->daily_calorie_goal} kcal/hari"
        : null;

    $lines = array_filter([
        "Siap, {$user->name}! Setup selesai. 🎉",
        "",
        "Akun: {$accountLine}",
        $calorieLine,
        "Ringkasan: setiap hari jam " . Carbon::parse($user->summary_time)->format('H:i'),
        "",
        "Sekarang coba ketik sesuatu:",
        "• makan ayam geprek 35k",
        "• grab 18rb",
        "• gajian 5jt",
    ]);

    $telegram->sendMessage([
        'chat_id' => $user->telegram_chat_id,
        'text'    => implode("\n", $lines),
    ]);
}
```

---

## Touchpoint 6 — Telegram Message Gate

Every incoming Telegram message must check `onboarding_step` first.
Non-complete users must not enter the 4-layer pipeline.

```php
// app/Http/Controllers/TelegramWebhookController.php

public function handle(Request $request): JsonResponse
{
    $update = $request->all();

    // Handle callback queries (button taps) separately
    if (isset($update['callback_query'])) {
        $this->handleCallbackQuery($update['callback_query']);
        return response()->json(['ok' => true]);
    }

    $message = $update['message'] ?? null;
    if (!$message || !isset($message['text'])) {
        return response()->json(['ok' => true]);
    }

    $chatId = $message['chat']['id'];
    $text   = trim($message['text']);
    $user   = User::where('telegram_chat_id', $chatId)->first();

    // ── GATE 1: Unknown user ──────────────────────────────────────
    if (!$user) {
        $this->telegram->sendMessage([
            'chat_id' => $chatId,
            'text'    => 'Ketik /start untuk mulai.',
        ]);
        return response()->json(['ok' => true]);
    }

    // Update last active
    $user->update(['last_active_at' => now()]);

    // ── GATE 2: Commands (always handle regardless of state) ──────
    if (str_starts_with($text, '/start')) {
        $this->handleStart($message['from']);
        return response()->json(['ok' => true]);
    }

    // ── GATE 3: Onboarding incomplete ────────────────────────────
    if ($user->onboarding_step !== 'complete') {
        $this->handleIncompleteOnboarding($user);
        return response()->json(['ok' => true]);
    }

    // ── GATE 4: Full pipeline for complete users ──────────────────
    ProcessUserMessage::dispatch($user, $text, $message)->onQueue('high');

    return response()->json(['ok' => true]);
}

private function handleIncompleteOnboarding(User $user): void
{
    // If link was sent or webview opened → remind them
    $url = URL::temporarySignedRoute(
        'onboarding.start',
        now()->addMinutes(10),
        ['telegram_id' => $user->telegram_chat_id]
    );

    $this->telegram->sendMessage([
        'chat_id'      => $user->telegram_chat_id,
        'text'         => 'Setup dulu ya sebelum mulai. Cuma 3 menit.',
        'reply_markup' => json_encode([
            'inline_keyboard' => [[
                ['text' => '🚀 Lanjut Setup', 'url' => $url]
            ]]
        ])
    ]);
}
```

---

## Touchpoint 7 — Dashboard Intent in Telegram

Handled inside the main pipeline's resolve layer when `intent = 'open_dashboard'`.

```php
// app/Jobs/ProcessUserMessage.php — after extract layer returns intent

if ($extracted['intent'] === 'open_dashboard') {
    $url = URL::temporarySignedRoute(
        'dashboard.auth',
        now()->addMinutes(30),
        ['telegram_id' => $this->user->telegram_chat_id]
    );

    $this->telegram->sendMessage([
        'chat_id'      => $this->user->telegram_chat_id,
        'text'         => 'Link aktif 30 menit.',
        'reply_markup' => json_encode([
            'inline_keyboard' => [[
                ['text' => '📊 Buka Dashboard', 'url' => $url]
            ]]
        ])
    ]);
    return;
}
```

The extract layer LLM maps these phrases to `open_dashboard`:

```
"buka dashboard", "lihat dashboard", "open dashboard",
"/dashboard", "mau lihat data", "dashboard dong"
```

Include in extract prompt:

```
If the user wants to view their dashboard, history, or data visually,
classify intent as "open_dashboard".
```

---

## Touchpoint 8 — Undo vs Dashboard Edit Conflict

**Rule:** Telegram undo wins within the 5-minute window.
Dashboard edit wins after the window expires.

```php
// In dashboard history edit handler:

public function updateEntry(Request $request, Entry $entry): JsonResponse
{
    // Gate: belongs to current dashboard user
    abort_if($entry->user_id !== $request->dashboard_user->id, 403);

    // If within undo window → block edit from dashboard, undo takes priority
    if ($entry->undo_expires_at && now()->lt($entry->undo_expires_at)) {
        return response()->json([
            'error' => 'Transaksi ini masih bisa di-undo dari Telegram. Tunggu beberapa menit.',
        ], 409);
    }

    // Safe to edit
    $entry->update($request->only(['amount', 'category', 'note', 'merchant']));

    return response()->json(['ok' => true]);
}
```

---

## Touchpoint 9 — Resolve Layer Using Webview-Set Data

This is the complete resolve function — reads only from DB, no LLM involved.

```php
// app/Services/ResolveLayer.php

class ResolveLayer
{
    public function resolve(User $user, array $extracted): array
    {
        return [
            ...$extracted,
            'account_id'      => $this->resolveAccount($user, $extracted),
            'account_source'  => $this->resolveAccountSource($user, $extracted),
            'calories'        => $this->resolveCalories($user, $extracted),
            'calorie_source'  => $this->resolveCalorieSource($user, $extracted),
        ];
    }

    private function resolveAccount(User $user, array $extracted): int
    {
        // 1. User stated account explicitly in message
        if (!empty($extracted['explicit_account_name'])) {
            $account = $user->accounts()
                ->where('name', 'like', "%{$extracted['explicit_account_name']}%")
                ->first();
            if ($account) return $account->id;
        }

        // 2. Merchant-level learned preference
        if (!empty($extracted['merchant'])) {
            $pref = BehavioralMemory::where([
                'user_id' => $user->id,
                'domain'  => 'merchant_account',
                'subject' => $extracted['merchant'],
            ])->where('behavioral_confidence', '>=', 0.50)->first();

            if ($pref) return $pref->value['account_id'];
        }

        // 3. Category-level learned preference
        if (!empty($extracted['category'])) {
            $pref = BehavioralMemory::where([
                'user_id' => $user->id,
                'domain'  => 'category_account',
                'subject' => $extracted['category'],
            ])->where('behavioral_confidence', '>=', 0.50)->first();

            if ($pref) return $pref->value['account_id'];
        }

        // 4. Default account (always set during webview onboarding)
        return $user->default_account_id;
    }

    private function resolveAccountSource(User $user, array $extracted): string
    {
        if (!empty($extracted['explicit_account_name'])) return 'explicit';

        if (!empty($extracted['merchant'])) {
            $pref = BehavioralMemory::where([
                'user_id' => $user->id,
                'domain'  => 'merchant_account',
                'subject' => $extracted['merchant'],
            ])->where('behavioral_confidence', '>=', 0.50)->first();
            if ($pref) return 'learned';
        }

        if (!empty($extracted['category'])) {
            $pref = BehavioralMemory::where([
                'user_id' => $user->id,
                'domain'  => 'category_account',
                'subject' => $extracted['category'],
            ])->where('behavioral_confidence', '>=', 0.50)->first();
            if ($pref) return 'learned';
        }

        return 'default';
    }

    private function resolveCalories(User $user, array $extracted): ?int
    {
        if (empty($extracted['item'])) return null;

        // User-corrected value takes priority over AI estimate
        $memory = BehavioralMemory::where([
            'user_id' => $user->id,
            'domain'  => 'food_calories',
            'subject' => strtolower($extracted['item']),
        ])->first();

        return $memory
            ? $memory->value['kcal']
            : $extracted['calories_estimate'];
    }

    private function resolveCalorieSource(User $user, array $extracted): string
    {
        if (empty($extracted['item'])) return 'unknown';

        $memory = BehavioralMemory::where([
            'user_id' => $user->id,
            'domain'  => 'food_calories',
            'subject' => strtolower($extracted['item']),
        ])->first();

        return $memory ? 'user_preference' : 'ai_estimate';
    }
}
```

---

## Touchpoint 10 — Policy Layer Using Resolved Data

```php
// app/Services/PolicyEngine.php

class PolicyEngine
{
    public function determineMode(array $resolved): string
    {
        // Explicit overrides everything
        if ($resolved['account_source'] === 'explicit') {
            return 'explicit_input';
        }

        // Learned with auto_apply consent → silent
        if ($resolved['account_source'] === 'learned') {
            $memory = BehavioralMemory::where([
                'user_id' => $resolved['user_id'],
                'domain'  => 'merchant_account',
                'subject' => $resolved['merchant'] ?? '',
            ])->first();

            if ($memory?->auto_apply) {
                return 'auto_apply';
            }

            return 'soft_confirmation';
        }

        // Default account used — soft confirm if new user (<7 days)
        $user = User::find($resolved['user_id']);
        if ($user->onboarding_complete_at?->diffInDays(now()) < 7) {
            return 'soft_confirmation';
        }

        return 'auto_apply'; // default account, settled user — just do it
    }
}
```

---

## Complete Message Processing Flow (Wired Together)

```php
// app/Jobs/ProcessUserMessage.php

public function handle(
    ExtractLayer    $extractor,
    ResolveLayer    $resolver,
    PolicyEngine    $policy,
    FormatLayer     $formatter,
    TelegramService $telegram
): void {
    $user = $this->user;
    $text = $this->text;

    // ── EXTRACT ───────────────────────────────────────────────────
    $extracted = $extractor->parse($text, $user);  // calls LLM

    // Handle dashboard open intent early
    if ($extracted['intent'] === 'open_dashboard') {
        $this->sendDashboardLink($user, $telegram);
        return;
    }

    // Handle query intents (summary, balance)
    if (in_array($extracted['intent'], ['query_summary', 'query_balance'])) {
        $this->handleQuery($extracted, $user, $formatter, $telegram);
        return;
    }

    // Handle unknown
    if ($extracted['intent'] === 'unknown' || $extracted['needs_clarification']) {
        $telegram->sendMessage([
            'chat_id' => $user->telegram_chat_id,
            'text'    => $extracted['clarification_question']
                ?? "Butler nggak nangkep yang ini. Contoh: '45k makan siang' atau 'grab 18rb'",
        ]);
        return;
    }

    // ── RESOLVE ───────────────────────────────────────────────────
    $resolved = $resolver->resolve($user, array_merge($extracted, ['user_id' => $user->id]));

    // ── POLICY ────────────────────────────────────────────────────
    $interactionMode = $policy->determineMode($resolved);

    // ── SAVE ENTRY (pending confirmation if needed) ───────────────
    $entry = Entry::create([
        'user_id'          => $user->id,
        'type'             => $resolved['intent'],
        'amount'           => $resolved['amount'],
        'account_id'       => $resolved['account_id'],
        'category'         => $resolved['category'],
        'merchant'         => $resolved['merchant'],
        'food_item'        => $resolved['item'],
        'calories'         => $resolved['calories'],
        'is_calorie_estimated' => $resolved['calorie_source'] !== 'user_preference',
        'entry_time'       => $resolved['entry_time'] ?? now(),
        'ai_raw_input'     => $text,
        'ai_intent'        => $resolved['intent'],
        'ai_confidence'    => $resolved['confidence'],
        'undo_token'       => Str::random(16),
        'undo_expires_at'  => now()->addMinutes(config('butler.undo_window_minutes')),
        'confirmed_at'     => in_array($interactionMode, ['auto_apply', 'explicit_input'])
                                ? now()
                                : null,
    ]);

    // Update account balance if auto-confirmed
    if ($entry->confirmed_at) {
        UpdateAccountBalance::dispatch($entry)->onQueue('default');
        UpdateBehavioralMemory::dispatch($entry)->onQueue('low');
    }

    // ── FORMAT ────────────────────────────────────────────────────
    $message = $formatter->format($resolved, $interactionMode, $entry);  // calls LLM

    // ── SEND ──────────────────────────────────────────────────────
    $keyboard = $this->buildKeyboard($interactionMode, $entry);

    $telegram->sendMessage([
        'chat_id'      => $user->telegram_chat_id,
        'text'         => $message,
        'reply_markup' => $keyboard ? json_encode($keyboard) : null,
    ]);
}

private function buildKeyboard(string $mode, Entry $entry): ?array
{
    // Auto-apply: only undo
    if ($mode === 'auto_apply') {
        return [
            'inline_keyboard' => [[
                ['text' => '↩ Undo', 'callback_data' => "undo:{$entry->undo_token}"]
            ]]
        ];
    }

    // Soft confirmation: confirm + undo
    if ($mode === 'soft_confirmation') {
        return [
            'inline_keyboard' => [[
                ['text' => '✓ Ya',         'callback_data' => "confirm:{$entry->id}"],
                ['text' => '↩ Batal',      'callback_data' => "undo:{$entry->undo_token}"],
            ]]
        ];
    }

    // Explicit input: just undo
    return [
        'inline_keyboard' => [[
            ['text' => '↩ Undo', 'callback_data' => "undo:{$entry->undo_token}"]
        ]]
    ];
}
```

---

## Connection Map Summary

```
TRIGGER             TELEGRAM SIDE               WEBVIEW SIDE
────────────────────────────────────────────────────────────────
/start (new)   →  sendOnboardingLink()      →  /setup/{token}
/start (mid)   →  resendOnboardingLink()    →  /setup/{token} (resumes)
/start (done)  →  sendWelcomeBack()         →  —
Any msg (mid)  →  handleIncompleteOnboarding() → resend link
Any msg (done) →  ProcessUserMessage job    →  —
"buka dashboard" → sendDashboardLink()     →  /dashboard/auth/{token}
Webview /done  →  SendOnboardingCompleteNotification job
Webview saves  →  users table updated      →  resolve layer reads immediately
Daily job      →  reads users.summary_time →  set in /notifications step
Undo tap       →  reverses entry           →  dashboard edit blocked during window
Dashboard edit →  —                        →  updates entry after undo window
```

---

*Last updated: May 2026 — Integration Guide v1.0*
