# Project Butler — Reference v2.0

> Base: v1.3 philosophy locked. Do not change the core.
> This version adds: soul table, backend-owns-logic rule, undo architecture,
> explicit consent flow, correction/unlearning, queue priority.

---

## What Is Locked (From v1.3)

Do not revisit these. They are final.

```
Telegram     = operational layer. Log, ask, receive. Nothing else.
Webview      = configuration layer. All setup lives here.
Onboarding   = webview only. Telegram sends one signed URL. Done.
Auth         = telegram_chat_id. No passwords. No registration.
AI role      = extract intent + entities + format responses. Not decide.
Backend role = all financial logic, rule resolution, account selection.
```

---

## The Strict Separation Rule

The architectural review names this clearly. Enforce it in every PR.

```
USER: "gofood ayam geprek 45k"

STEP 1 — LLM EXTRACTS (only)
→ { merchant: "GoFood", category: "food", amount: 45000, item: "ayam geprek" }

STEP 2 — BACKEND RESOLVES (no LLM)
→ Check soul table: GoFood → GoPay (confidence 0.91, consent: true)
→ Apply account: GoPay
→ Estimate calories: 520kcal (soul: user corrected to 520, 3x)
→ Resolved entry ready

STEP 3 — LLM FORMATS (only)
→ Writes the confirmation message using resolved data
→ "Tercatat: Ayam Geprek • Rp45.000 • GoPay • ~520 kcal"
```

**Why this matters:**
- Bugs are reproducible (logic is in your code, not in a prompt)
- API cost drops (smaller prompts, fewer tokens)
- Financial decisions are deterministic and testable
- The LLM never touches a balance or decides which account

---

## The Soul Table

This is Butler's memory. It stores everything Butler has learned about
a user and feeds it as context into every AI call.

Every interaction that reveals something about the user → written here.
Every AI call → reads the relevant rows here before processing.

### Schema

```sql
CREATE TABLE soul (
  id                  BIGINT PRIMARY KEY,
  user_id             BIGINT NOT NULL REFERENCES users(id),

  -- Classification
  domain              ENUM(
                        'merchant_account',   -- GoFood → GoPay
                        'food_portion',       -- nasi goreng → avg 320gr
                        'food_calories',      -- ayam geprek → 520kcal (user-corrected)
                        'meal_timing',        -- lunch → avg 12:28
                        'category_account',   -- food → GoPay (fallback)
                        'spend_rhythm',       -- friday → avg Rp180k
                        'correction_log'      -- history of what user corrected
                      ) NOT NULL,

  -- The pattern key (what this is about)
  subject             VARCHAR(128) NOT NULL,  -- "GoFood", "nasi goreng", "lunch", "friday"

  -- The learned value (flexible per domain)
  value               JSONB NOT NULL,
  /*
    merchant_account:  { account_id: 3, account_name: "GoPay" }
    food_portion:      { avg_grams: 320, sample_count: 7 }
    food_calories:     { kcal: 520, source: "user_correction", ai_estimate: 480 }
    meal_timing:       { avg_hour: 12, avg_minute: 28, std_dev_min: 18 }
    category_account:  { account_id: 3, account_name: "GoPay" }
    spend_rhythm:      { avg_idr: 180000, observation_weeks: 4 }
    correction_log:    { field: "calories", old: 480, new: 520, entry_id: 991 }
  */

  -- Context modifiers (for future: "Starbucks morning=BCA, night=GoPay")
  context_json        JSONB DEFAULT '{}',
  /*
    {}                      = no context, always applies
    { "hour_from": 6,
      "hour_to": 12 }       = only applies in morning
    { "day_of_week": [5,6] }= only Friday and Saturday
  */

  -- Trust
  confidence          DECIMAL(4,3) DEFAULT 0.100,
  observation_count   INTEGER DEFAULT 1,
  user_consented      BOOLEAN DEFAULT FALSE,  -- explicit "yes remember this"
  auto_apply          BOOLEAN DEFAULT FALSE,  -- true only after consent

  -- Decay support
  last_observed_at    TIMESTAMP NOT NULL,
  created_at          TIMESTAMP NOT NULL,
  updated_at          TIMESTAMP NOT NULL,

  UNIQUE (user_id, domain, subject, context_json)
);
```

---

### How Confidence Works

```
Initial observation:     confidence = 0.1
Each new confirmation:   confidence += 0.1 (capped at 1.0)
Each correction:         confidence -= 0.3 (floor at 0.0)
User explicit consent:   confidence = 1.0 (locked until corrected)
User explicit denial:    delete row
```

```
confidence < 0.50   → never auto-apply, never suggest
confidence 0.50–0.79 → suggest only, wait for confirm
confidence 0.80–0.99 → ask consent to auto-apply (once)
confidence = 1.00   → auto-apply silently (consent already given)
```

---

### The Consent Gate

Never auto-apply silently just because observation_count >= N.
The user must explicitly say yes before `auto_apply = true`.

**Trigger:** confidence crosses 0.80 for the first time.

```
Butler: Aku notice kamu selalu bayar GoFood pakai GoPay.
        Mau aku otomatis pilih GoPay untuk GoFood ke depannya?
        [Ya, otomatis] [Nggak usah]
```

On [Ya]: `user_consented = true`, `auto_apply = true`, `confidence = 1.0`
On [Nggak]: delete the soul row. Never ask again for this subject.

---

### The Correction Loop (Unlearning)

If a user corrects Butler ("bukan GoPay, cash"), the soul must unlearn:

```
1. Find soul row: domain=merchant_account, subject="GoFood"
2. confidence -= 0.3
3. If confidence <= 0.0: delete row
4. Insert correction_log row:
   { old_account: "GoPay", new_account: "Cash", entry_id: X }
5. Find/create soul row: domain=merchant_account, subject="GoFood",
   value.account_name="Cash"
6. confidence += 0.1 for the Cash row
```

This is run by `ProcessSoulCorrection` — a dedicated job,
not mixed into the main parsing pipeline.

---

### How Soul Feeds Into AI Calls

Before every parse call, build a soul context block:

```php
function getSoulContext(int $userId): array
{
    return Soul::where('user_id', $userId)
        ->where('confidence', '>=', 0.5)
        ->whereIn('domain', [
            'food_calories',
            'food_portion',
            'meal_timing',
            'spend_rhythm',
        ])
        ->orderByDesc('confidence')
        ->limit(12) // keep prompt lean
        ->get()
        ->groupBy('domain')
        ->toArray();
}
```

The merchant/account resolution does NOT go to the LLM.
Backend resolves it from soul before the format call.

Inject into format prompt only:

```
USER MEMORY (do not override unless message explicitly says otherwise):
- ayam geprek → 520kcal (user corrected, high confidence)
- nasi goreng → avg 320gr → ~445kcal
- lunch is usually around 12:28 for this user

Use these when the user message doesn't specify exact values.
Flag with calorie_source: "soul" in your response.
```

---

## Undo Architecture

Every confirmed entry gets an undo window.
This is the fix for "low-friction logging leads to mistakes."

### Schema addition on `entries`

```sql
undo_expires_at     TIMESTAMP NULLABLE,   -- set to NOW() + 5 minutes on confirm
undo_token          VARCHAR(64) NULLABLE, -- short unique token, shown on confirm message
is_undone           BOOLEAN DEFAULT FALSE
```

### Flow

After confirmation message, append:

```
Tercatat: Grab • Rp23.000 • GoPay
[↩ Undo] ← inline keyboard, token embedded in callback_data
```

The [↩ Undo] button is visible for 5 minutes.
After 5 minutes: button disappears, `undo_expires_at` passed,
entry is permanent (soft-delete is still possible via dashboard).

On undo tap:
1. Validate `undo_expires_at > NOW()`
2. Set `is_undone = true`, `deleted_at = NOW()`
3. Reverse the account balance change
4. DO NOT reverse soul updates (one observation doesn't matter)
5. Reply: "Oke, dibatalin. Mau catat ulang?"

---

## Queue Priority (Enforce This in Code)

```
HIGH     Telegram webhook response (parsing, immediate reply)
MEDIUM   Account balance update after confirmed entry
MEDIUM   Soul update (UpdateSoulPatterns job)
LOW      Daily summary generation
LOW      Behavior nudges and reminder evaluation
LOWEST   Analytics aggregation, pattern analysis
```

In Laravel, use separate queue names:

```php
// config/queue.php — define connections or use queue names
'high'    → processes immediately
'default' → 3-second delay acceptable
'low'     → can wait, batch-friendly
```

Dispatch accordingly:

```php
UpdateSoulPatterns::dispatch($entry)->onQueue('low');
SendDailySummary::dispatch($user)->onQueue('low');
ProcessWebhook::dispatch($payload)->onQueue('high');
```

Never let a soul update or summary job block a webhook response.

---

## Updated Tables Summary

### New in v2.0

| Table | Purpose |
|---|---|
| `soul` | Behavioral memory — feeds AI context, drives auto-apply |
| `accounts` | Real money locations (BCA, GoPay, Cash) |
| `user_merchant_defaults` | Deprecated — replaced by soul domain=merchant_account |

### Updated in v2.0

| Table | What changed |
|---|---|
| `entries` | + `account_id`, `undo_token`, `undo_expires_at`, `is_undone` |
| `users` | + `default_account_id` FK → accounts |
| `reminders` | + `consent_asked_at` (track when consent was requested) |

### Unchanged

`streaks`, `daily_summaries`, `ai_logs`, `bills`, `debts`, `funds`

---

## Webview Pages (Functional Scope, v1+v2 Combined)

Functional only. Blade + Alpine.js. No design system required.

```
/setup/{token}/profile       name, currency, timezone
/setup/{token}/accounts      add accounts, pick default
/setup/{token}/budget        monthly target (optional)
/setup/{token}/health        calorie toggle, goal, aim
/setup/{token}/notifications summary time
/setup/{token}/done          finish + open Telegram

/dashboard                   (signed link, 30min session)
/dashboard/accounts          view balances, edit, add
/dashboard/bills             recurring bills, due dates, paid status
/dashboard/funds             sinking funds + goals, progress
/dashboard/history           transaction log, filter by type/date, edit
/dashboard/settings          re-edit all setup preferences
```

Dashboard auth: `URL::temporarySignedRoute()` valid 30 min.
Butler sends it when user says "buka dashboard" or "/dashboard".

---

## Confidence Score Behavior (Canonical — Final)

| Score | What Backend does | What Butler says |
|---|---|---|
| < 0.50 | Never apply | Ask explicitly |
| 0.50–0.79 | Suggest, await confirm | Shows suggestion as pre-selected |
| 0.80–0.99 | Ask consent once | "Mau aku otomatis pakai X untuk Y?" |
| 1.00 | Auto-apply silently | Shown in confirmation, no extra prompt |

---

## MVP Must-Have (Final List)

```
✓ Webview onboarding (profile + accounts + health + notifications)
✓ Expense logging via natural language
✓ Account deduction with default account fallback
✓ Calorie estimation with soul correction
✓ Soul table + UpdateSoulPatterns job
✓ Undo button (5-minute window)
✓ Explicit consent gate before auto-apply
✓ Daily summary at user-set time
✓ /dashboard with history (read-only acceptable for demo)
```

## Not in MVP

```
✗ Bills auto-setup (logging OK, dashboard setup = v2 webview)
✗ Sinking funds (dashboard setup = v2 webview)
✗ Financial goals
✗ Debt engine
✗ context_json on soul (time-of-day rules)
✗ spend_rhythm soul domain (needs 4+ weeks data)
✗ OCR receipts
✗ Voice logging
✗ iOS client
```

---

*Last updated: May 2026 — v2.0 Soul Table + Architecture Hardening*
