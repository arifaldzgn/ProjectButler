# Architecture Notes — Project Butler

Last updated: 2026-05-28

---

## Core Philosophy (Locked since v1.3)

| Layer | Responsibility |
|-------|----------------|
| **Telegram** | Input and output only — no business logic |
| **Webview** | Configuration only — no transaction creation |
| **Backend (Laravel)** | Owns all financial logic and rule resolution |
| **LLM (via OpenRouter)** | Extract intent + format responses — never decide |
| **Identity** | `telegram_chat_id` = auth. No passwords. |

---

## System Overview

```
User (Telegram) ──► Webhook POST /telegram/webhook
                        │
                        ▼
              TelegramWebhookController
              (4-gate state machine)
              Gate 1: /start
              Gate 2: unknown user
              Gate 3: onboarding incomplete
              Gate 4: dispatch to high-priority queue
                        │
                        ▼
           ProcessTelegramMessage (Job, queue:high)
                        │
                        ▼
                 MessageRouter.handle()
                 ├── Gate 1: onboarding → OnboardingService
                 ├── Gate 2: quick commands (bypass AI)
                 ├── Gate 3: AI parse → OpenRouterClient → AIService
                 └── Gate 4: route by intent → handler
                        │
              ┌─────────┴──────────────┐
              ▼                        ▼
     EntryService              Other handlers
     (create → confirm)        (bills, funds, balance, etc.)
              │
    ┌─────────┴───────────┐
    ▼                     ▼
FundService          UpdateBehavioralMemory
(debit/credit)       (Job, queue:low)
                          │
                          ▼
                 BehavioralMemoryService
                 (observe/strengthen/consent)
```

---

## Smart Fallback (v2.3)

When `MessageRouter` can't confidently route a message — parse exception, `confidence < 0.50`, or `intent === 'unknown'` — it invokes `CommandSuggestionService::suggest()` before sending the canned "bingung" reply.

**Two-stage hybrid:**
1. **Deterministic match** against a static catalog of ~18 capabilities (label, triggers, example). Scoring = 0.6×best-token-Levenshtein + 0.4×Jaccard overlap + substring-contains bonus. Threshold 0.55. Runs < 5ms, no AI cost.
2. **AI fallback** (only on Stage-1 miss) — one `OpenRouterClient::generateJson()` call asks the AI to classify as `did_you_mean` / `unsupported` / `unclear` and nominate the closest supported feature.

If still nothing matches, the original canned reply is sent (no regression).

Every fallback event is logged to `ai_logs` with `intent_detected = 'unrecognized'`, the original confidence, and a JSON-encoded suggestion payload in `error_message`. The `/admin/unrecognized` page groups these by normalized phrase + frequency so admins can see what features users keep reaching for.

---

## Message Routing Flow (v2.1)

### Quick Commands (no AI cost)
Handled before AI parse in `MessageRouter::handleQuickCommand()`:

| Keyword(s) | Handler |
|------------|---------|
| `saldo`, `balance`, `/saldo` | `handleQueryBalance()` |
| `summary`, `ringkasan` | `sendQuickSummary()` |
| `help`, `bantuan` | `sendHelp()` (contextual — shows today stats) |
| `tagihan`, `daftar tagihan` | `sendBillList()` |
| `settings`, `pengaturan` | `sendSettingsLink()` (signed dashboard URL) |
| `buka dashboard` | inline URL button |
| `mood: ...` | `handleMoodLog()` (parses mood + energy → mood_logs) |
| Natural language balance phrases | `handleQueryBalance()` |

### Entry Confirmation Flow (v2.1)
```
Message received
    → AI parse (intent, confidence, entities)
    → createPendingEntry()
    → resolveAccountForEntry() [Layer 2 Resolve]
        ├── explicit_input  → confirm directly
        ├── auto_apply      → confirm directly
        ├── soft_confirmation → show suggested account in confirmation text
        └── needs_clarification → show account selection keyboard
    → [User taps Confirm]
    → confirmEntry() → applyFundEffect() → applyBillDebtEffect()
    → Send confirmed message with [↩ Undo] [✏️ Edit] buttons
    → Dispatch UpdateBehavioralMemory (queue:low)
```

### Undo Flow (v2.1)
- Entry stores `undo_token` and `undo_expires_at` (5 min)
- Undo button: `undo:{token}`
- On tap: reverse all `fund_transactions` for the entry, call `entry->markUndone()`
- After undo window expires: undo button shows "Waktu undo sudah habis"
- [✏️ Edit] button is a signed dashboard URL — always available for 30 min

---

## Configurable Runtime Parameters (v2.4)

| Config key | Env var | Default | Effect |
|---|---|---|---|
| `butler.suggestion_threshold` | `BUTLER_SUGGESTION_THRESHOLD` | `0.55` | Stage-1 fuzzy match threshold in `CommandSuggestionService` |
| `butler.undo_window_minutes` | `BUTLER_UNDO_WINDOW_MINUTES` | `5` | Minutes the undo button stays active after confirmation |

---

## Per-User Scheduled Behavior (v2.4)

Both the **daily summary** and **bill reminders** now run inside `everyMinute()` scheduler loops. Each service performs internal time-matching:
- `DailySummaryService::isUserSummaryTime()` compares `Carbon::now($user->timezone)->format('H:i')` to `$user->summary_time`.
- `ReminderService::processBillDueReminders()` compares the same to each bill's `reminder_time` (fallback `09:00`).

No external cron logic is needed — the minute-level scheduler is already used for `processTimeBasedReminders()`.

---

## Behavioral Memory (Soul Table)

**Table:** `behavioral_memory`

**Domains:**
- `merchant_account` — merchant X → always use account Y
- `food_calories` — food X → estimated Y kcal
- `food_portion` — typical portion for a food
- `meal_timing` — meal type at certain time of day
- `category_account` — category X → account Y
- `spend_rhythm` — typical daily/weekly spend patterns

**Confidence thresholds:**
- 0.50 — eligible to suggest (show in confirmation)
- 0.80 — trigger consent gate ("Boleh aku otomatisin?")
- 1.00 — auto-apply without asking

**Learn/Unlearn cycle:**
1. Entry confirmed → `UpdateBehavioralMemory` dispatched (queue:low)
2. Job calls `BehavioralMemoryService::observe()` → strengthens pattern
3. If confidence ≥ 0.80 and not yet consented → fires consent message
4. User picks a different account → `ProcessBehavioralCorrection` dispatched
5. Job calls `BehavioralMemoryService::correct()` → weakens old, strengthens new
6. Sends Telegram confirmation of the update

---

## Scheduler (routes/console.php)

| Schedule | Task |
|----------|------|
| Daily 21:00 (user TZ) | `DailySummaryService::sendAndStore()` |
| Every 15 min | `ReminderService::processBehaviorBasedReminders()` |
| Every 1 min | `ReminderService::processTimeBasedReminders()` |
| Daily 09:00 | `ReminderService::processBillDueReminders()` |
| Daily 09:00 | `ReminderService::processDebtDueReminders()` |
| Daily 10:00 | `ReminderService::processSetupIncompleteReminders()` |
| 1st of month 00:05 | `BillService::resetMonthlyPaymentStatus()` + `DebtService::resetMonthlyPaymentStatus()` |

**Queue workers required:**
- `high` — webhook processing (must be fast)
- `default` / `medium` — balance updates, corrections
- `low` — behavioral memory, daily summaries

---

## Authentication

- **Telegram → Backend:** webhook is public; optionally filtered by `is_allowed_chat()`
- **Webview (Onboarding):** signed URL (10-min expiry) → session-based continuation
- **Webview (Dashboard):** signed URL (30-min expiry) → sliding session (30 min from last activity)
- **Admin:** `is_admin` flag on User + `is_admin` middleware

---

## Daily Summary Context (AI Prompt B)

The AI receives a structured context object. As of v2.2.1, `totals` includes:

```json
{
  "user": { "name", "monthly_income_idr", "daily_budget_idr", "tracking_mode", "daily_calorie_goal", "calorie_goal_type" },
  "date": "...",
  "entries": [...],
  "totals": {
    "spent_idr": 45000,
    "income_idr": 0,
    "month_income_idr": 5000000,
    "month_savings_idr": 500000,
    "budget_remaining_idr": 55000,
    "calories_consumed": 1200,
    "calorie_remaining": 800
  },
  "funds": { ... },
  "upcoming": { "bills_due_3_days": [...], "debts_due_3_days": [...] },
  "streak": { "log_current": 7, "log_longest": 14 },
  "setup_flags": { "has_income_set", "has_bills_setup", "has_emergency_fund", "has_debt_declared" }
}
```

---

## Streak Grace Period (v2.1.2)

`Streak::updateStreak()` logic:
- `last_date == today` → no change
- `last_date == yesterday` → increment (consecutive)
- `last_date == 2 days ago` → increment (1-day grace — missed exactly one day)
- `last_date < 2 days ago` → reset to 1

---

## Domain-Specific Streaks (v2.4)

`streaks` table now tracks 5 domains:

| Domain | Trigger | "Hit" condition |
|---|---|---|
| `log` | any confirmed entry | at least 1 entry today |
| `expense` | confirmed expense | at least 1 expense today |
| `meal` | confirmed meal | at least 1 meal today |
| `budget` | any confirmed entry | today's spending ≤ daily_budget_idr |
| `calorie` | any confirmed entry | calories hit the goal (logic varies by goal type) |

All use the same `Streak::updateStreak($type, $timezone)` method with 1-day grace period.

---

## Dashboard Pages

| Route | Controller | Description |
|-------|-----------|-------------|
| `/dashboard` | `DashboardController@index` | Today stats, accounts, sinking funds, recent activity |
| `/dashboard/history` | `DashboardController@history` | Paginated, filterable entry list |
| `/dashboard/spending` | `DashboardController@spending` | Monthly budget vs spend, category breakdown, daily chart, week-over-week banner |
| `/dashboard/nutrition` | `DashboardController@nutrition` | Calorie goal donut, 30-day chart, today's meal list |
| `/dashboard/insights` | `DashboardController@insights` | Spending trend %, calorie avg, combo chart, AI rule-based insights, mood chart |
| `/dashboard/memory` | `DashboardController@memory` (GET) + `deleteMemory` (DELETE) | View + delete learned behavioral patterns |
| `/dashboard/settings` | `DashboardController@settings` (GET/POST) | Edit profile, budgets, calorie goal, notifications, re-enter onboarding steps |
| `/admin/users` | `AdminController@index` | User list with counts |
| `/admin/ai-logs` | `AdminController@aiLogs` | AI parse/summary logs with filters, latency, confidence, failures |
| `/admin/unrecognized` | `AdminController@unrecognized` | Grouped phrases that triggered the smart-fallback engine, with frequency / reason / suggestion stats (v2.3) |

---

## Database Tables (current)

| Table | Purpose |
|-------|---------|
| `users` | Profile, settings, onboarding state, goals |
| `entries` | Every logged event (9 types) with AI provenance |
| `accounts` | Spending budget accounts (separate from funds) |
| `funds` | Spending budget, savings, emergency, sinking funds, financial goals |
| `fund_transactions` | Every credit/debit on a fund (reversible for undo) |
| `bills` | Recurring bills with due dates |
| `debts` | Installment debts |
| `behavioral_memory` | Learned patterns with confidence scores |
| `daily_summaries` | Stored summaries + delivery status |
| `ai_logs` | Every AI call (input, output, latency, confidence, errors) |
| `reminders` | Time-based and behavior-based reminder rules |
| `streaks` | Consecutive logging streaks per user |
| `mood_logs` | Daily mood + energy (keyed by telegram_chat_id + log_date) |
| `quick_commands` | Keyword aliases → handler mapping (admin-editable, no deploy needed) |
| `categories` | User-defined (and default) expense categories with emoji + color |
| `recurring_entries` | Scheduled auto-entry templates (daily/weekly/monthly) |
| `financial_health_scores` | Point-in-time composite score (0-100) per user with component breakdown |

---

## v2.5.0 Architecture Additions

### Token Usage Tracking
Every OpenRouter API call now returns `token_usage: { input, output }` alongside the result. `AIService` stores it in `lastTokenUsage` property. All callers pass it to `AiLogService`, which persists to `ai_logs.token_count_input/output`. Admin `/token-usage` page aggregates cost estimates.

### In-Telegram Calorie Edit (Cache State Machine)
Pattern: callback tap → `Cache::put("cal_correction:{chatId}", entryId, 5 min)` → next message intercepted before AI parse. This pattern can be reused for any short-lived 2-step interaction (e.g. receipt confirmation, rename a fund).

### Custom Categories
- `Category::seedDefaults($user)` called once at onboarding completion.
- AI prompt includes user's custom category names (injected via `parseMessage(array $userCategories)`).
- `EntryService::resolveCustomCategoryId()` fuzzy-matches the AI-returned text label to a DB row.
- Entries have both a text `category` column (legacy, AI-returned) and a nullable `category_id` FK.

### Recurring Entries
`RecurringEntryService::processRecurringEntries()` runs hourly. It creates a pre-confirmed `Entry` (no ✅/❌ needed — auto-confirmed) and advances `next_run_at` using `RecurringEntry::advanceNextRun()`.

### Receipt Photo Scanning (Vision AI)
Flow: `photo` in webhook → `__photo:{fileId}|caption:...` sentinel string → `MessageRouter::handleReceiptPhoto()` → `ReceiptScanService::extractFromTelegramPhoto()` → `OpenRouterClient::generateVisionJson()` (multimodal) → `createPendingEntry()` → standard confirmation buttons.
The sentinel prefix `__photo:` prevents the message from being parsed as a text command or passed to the AI text parser.

### Financial Health Score
Composite 0-100 score with 6 factors (weights: savings rate 25%, emergency fund 20%, DTI 20%, budget adherence 15%, bill consistency 10%, logging streak 10%). Persisted to `financial_health_scores` with a 24h cache via `getOrCalculate()`. Displayed on dashboard index (summary card) and cashflow page (detailed breakdown).

### Weekly Summary
`WeeklySummaryService` mirrors `DailySummaryService` but uses a weekly scope. Both store to `daily_summaries` table distinguished by `summary_type` ('daily' vs 'weekly'). AI prompt for weekly uses a reflective analytical tone with week-over-week delta.

### Savings Goal Milestone Notifications
`FundService::creditFund()` calls `checkGoalMilestone()` after every credit. Milestones (25/50/75/100%) are guarded by Cache keys `goal_milestone:{fundId}:{pct}` with 2-year TTL to prevent re-sends across restarts.

---

## Entry Types

| Type | Logged by |
|------|-----------|
| `expense` | User (from Telegram) |
| `meal` | User (from Telegram) |
| `income` | User (from Telegram) |
| `saving` | User (from Telegram) |
| `bill_payment` | User (from Telegram) |
| `debt_payment` | User (from Telegram) |
| `sinking_fund_deposit` | User (from Telegram) |
| `transfer` | User (from Telegram) |
| `goal_deposit` | Reserved |

---

## What NOT to Build Yet

These are deferred to avoid premature complexity:

- **Transaction splitting** — across categories or accounts (complex UX, no user demand yet)
- **Partner/Household Mode** — multi-user shared budgets (requires auth overhaul)
- **Native Telegram Mini App** — current WebView approach is sufficient
- **Export (PDF)** — CSV export already exists via history page; PDF adds complexity for marginal gain
