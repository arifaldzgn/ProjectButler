# Changelog — Project Butler

Format: `[version] YYYY-MM-DD — what changed and why`

---

## [v2.4.0] 2026-05-27 — Full improvement pass (all 12 items)

### Added

**Behavioral Memory dashboard page (`/dashboard/memory`)**
- Shows all learned patterns grouped by domain with confidence bars, observation count, auto-apply status, and last-seen timestamp.
- Per-row delete button: removes the pattern without touching transaction history.
- Explanatory card at the bottom describing how confidence thresholds work.
- Linked in the main nav (Memory tab).

**Domain-specific streaks: Budget + Calorie**
- New columns on `streaks` table: `budget_current/longest/last_date`, `calorie_current/longest/last_date` (migration `2026_05_27_100001`).
- `StreakService` now checks budget adherence (stayed under daily budget) and calorie goal completion after every confirmed entry.
- Calorie hit definition respects `calorie_goal_type`: bulking = must meet or exceed, cutting = must stay under, maintenance = within ±150 kcal.
- Dashboard home shows budget streak + calorie streak cards alongside existing log/meal streaks.

**History page — enhanced filters + CSV export**
- New filter fields: min/max amount, account/fund dropdown.
- CSV export: `?export=csv` streams all filtered entries as a UTF-8 CSV with date, type, description, category, amount, calories, account columns.
- Export button in page header respects current filters.

**Calorie corrections feed behavioral memory**
- When a user corrects calories via the inline edit on the Nutrition page, `BehavioralMemoryService::observe()` is called for `food_calories` domain, strengthening the user-specific calorie value.
- Future AI estimates for the same food will be overridden by this learned value.

**Smart fallback threshold configurable**
- `CommandSuggestionService` now reads threshold from `config('butler.suggestion_threshold')` (env: `BUTLER_SUGGESTION_THRESHOLD`, default `0.55`).
- Deployments can tune the threshold without touching code.

**Undo window configurable**
- Undo window duration read from `config('butler.undo_window_minutes')` (env: `BUTLER_UNDO_WINDOW_MINUTES`, default `5`).

**Per-user daily summary time**
- `DailySummaryService::sendAndStore()` now filters per user's `summary_time` setting and processes them per-minute instead of at a fixed 21:00.
- Scheduler changed from `dailyAt('21:00')` to `everyMinute()`.
- Users who set 08:30 as their summary time now get it at 08:30, not 21:00.

**Per-bill reminder time**
- New `reminder_time` column on `bills` table (migration `2026_05_27_100002`, nullable, default system 09:00).
- `ReminderService::processBillDueReminders()` respects each bill's `reminder_time`, matching the current minute.
- Scheduler for bill reminders changed from `dailyAt('09:00')` to `everyMinute()`.

**Quick commands DB table**
- New `quick_commands` table (migration `2026_05_27_100003`) with alias, handler, description, sort_order, is_active.
- `QuickCommandSeeder` seeds all current aliases; run with `php artisan db:seed --class=QuickCommandSeeder`.
- Admins can deactivate or add aliases without a code deploy (MessageRouter still owns execution logic; DB controls the trigger set).
- `QuickCommand` Eloquent model added.

**Admin AI Logs — intent failure rate breakdown**
- New per-intent breakdown table (last 7 days, parse calls only): total, failures, failure %, avg confidence, avg latency.
- High-failure intents show red, so prompt tuning priority is visible at a glance.

**Admin Unrecognized — CSV export**
- Export button in filter bar: downloads all filtered grouped phrases as CSV with phrase, occurrences, unique users, last seen, suggestion kind, suggestion label.

**Settings — onboarding re-entry section**
- New card in Settings page: "Ulangi Langkah Setup" with buttons for each onboarding step (Profil, Akun, Budget, Kesehatan, Notifikasi).
- Links use the existing signed URL system (no new auth needed).

### Changed

- `config/butler.php`: added `suggestion_threshold` and `undo_window_minutes` keys.
- Scheduler (`routes/console.php`): daily summary and bill reminders now run `everyMinute()` with internal time-matching logic.
- `Bill` model: added `reminder_time` to `$fillable`.
- `Streak` model: added 6 new domain columns to `$fillable` and `casts()`.

### Why

Every item addresses a real friction point identified in the improvement analysis: streaks that don't track goal-specific behavior, calorie data that never feeds back into AI estimates, users unable to see or correct what the bot has learned, no way to export data for tax prep, and a fixed bot behavior that required code deploys to adjust.

---

## [v2.3.1] 2026-05-27 — Admin nav bar across all admin pages

### Added

**Shared admin navigation partial**
- New partial: `resources/views/admin/partials/nav.blade.php`.
- Renders a pill-style button row with all admin sections: 👥 Users · 🤖 AI Logs · 🤔 Unrecognized.
- Active page is highlighted with an accent border/background + a small dot indicator.
- Included at the top of every admin view (`admin.users.index`, `admin.ai-logs.index`, `admin.unrecognized.index`).
- Adding a new admin route in the future requires editing the `$adminNav` array in the partial only — all pages inherit it automatically.
- Removed the standalone "← AI Logs" / "→ Unrecognized Messages" one-off links that previously existed on individual pages.

### Why

Admin had three separate pages with no shared navigation — jumping between them required knowing the URLs or using the browser back button. A consistent top nav makes the admin area feel cohesive and scales automatically to any future `/admin/*` pages.

---

## [v2.3.0] 2026-05-27 — Smart fallback + unrecognized-message learning

### Added

**Smart fallback when Butler can't understand**
- New service: `app/Services/CommandSuggestionService.php`. Two-stage suggestion engine.
  - **Stage 1 (deterministic)** — tokenizes the user input and scores it against a catalog of ~18 supported capabilities (the 14 AI intents + quick-command shortcuts). Uses weighted Jaccard token-overlap (40%) + best per-token Levenshtein (60%) + substring-contains bonus. Returns top match above similarity 0.55. Runs in < 5ms. No AI cost.
  - **Stage 2 (AI fallback)** — only fires when Stage 1 returns nothing. Makes a single follow-up `OpenRouterClient::generateJson()` call asking the AI to classify the message as `did_you_mean` / `unsupported` / `unclear` and to nominate the closest supported feature.
- Wired into 3 fallback sites in `MessageRouter`:
  - Parse exception (AI threw / OpenRouter unreachable)
  - Low confidence (`confidence < 0.50`) inside `handleEntry`
  - AI returned `intent='unknown'`
- User-facing replies:
  - Did you mean: `🤔 Maksudmu: *Catat Tabungan*? Coba: \`nabung 500rb\``
  - Unsupported: `Butler belum bisa itu 😅 Tapi mungkin kamu bisa coba *Lihat Ringkasan*: \`ringkasan\``
- If suggestion engine returns null, falls back to the existing canned "bingung" reply — no regression in behaviour for genuinely random input.

**Unrecognized-messages admin log**
- New method on `AiLogService`: `logUnrecognized(?User, $rawInput, ?confidence, $suggestionPayload, $reason)`.
- Reuses the existing `ai_logs` table — no migration. Flag column: `intent_detected = 'unrecognized'` (distinct from the AI's own `'unknown'` intent). Suggestion-engine output is JSON-serialized into `error_message` for later inspection.
- New admin page: `/admin/unrecognized` — grouped view of phrases by normalized `raw_input`.
  - Header cards: total unrecognized (7d), unique phrases (7d), unique affected users (7d), top phrase + count
  - Filter: date range (default last 30d), min frequency
  - Table: phrase preview + reason (parse_exception / low_confidence / unknown_intent), occurrences, unique users, last seen, which suggestion was sent (with stage 1/2 indicator and `did_you_mean` / `unsupported` / `unclear` badge)
  - Pagination 50/page, sorted by frequency desc
- Link added on the existing `/admin/ai-logs` page header to navigate to the new view.

### Why

Before this, an unrecognized Telegram message returned a single canned reply with three format hints — users had no path forward and admins had no signal about what features users were trying to reach. The new fallback makes Butler feel dynamic ("did you mean X?", "we don't have that yet, try Y") while the admin log captures the pattern over time so the team can prioritize what to build next.

---

## [v2.2.1] 2026-05-27 — Expense balance fix + calorie goal type + calorie transparency

### Fixed

**Total Kekayaan — spending accounts only**
- `$totalBalance` in `DashboardController@index` was summing all active funds (including goals, sinking funds, emergency fund). Corrected to use spending budget accounts only (`$totalAccountBalance`).
- Hero card on dashboard now shows per-account breakdown in the right column (instead of a lumped "Kas & Dompet" total) and separates "Dana Dialokasikan" (savings/goals/sinking funds) with a visual divider.
- Accounts section footer row renamed to "Total Likuid" (spending accounts only) — semantically correct.
- Goals and sinking funds are clearly separate: they're earmarked money, not liquid cash.

### Added

**Calorie goal type: bulking / maintenance / cutting**
- New `calorie_goal_type` column on `users` table (`nullable enum: bulking|cutting|maintenance`, default `maintenance`).
- Migration: `2026_05_27_000001_add_calorie_goal_type_to_users.php`.
- Added to `User::$fillable`.
- Settings page: interactive 3-option tile selector (💪 Bulking / ⚖️ Maintenance / 🎯 Cutting) with live visual feedback on click.
- `DashboardController@saveSettings`: validates and saves the field.
- `DailySummaryService::buildSummaryContext()`: passes `calorie_goal_type` to the AI so it can frame calorie advice correctly (e.g., "you're 200 kcal short of your bulking target").
- Nutrition page header shows the current goal type as a colored pill (links to Settings).

**Calorie estimate transparency**
- Each meal row in the nutrition page shows `⚡ est. AI` or `✓ manual` inline badge.
- Estimated meals show a subtitle explaining the source: "Estimasi berdasarkan rata-rata database makanan umum".
- An info card at the bottom of Today's Meals explains the estimation methodology (USDA/FoodData Central/SE Asia sources) and how to correct.
- Inline ✏️ edit per meal: click opens an in-row form to correct `food_item` and `calories` without leaving the page. Saves via `PATCH /dashboard/entries/{entry}`. On save, badge flips from "est. AI" → "manual".
- `updateEntry()` now accepts `calories` (nullable integer) and `food_item` (nullable string 256). When `calories` is corrected, `is_calorie_estimated` is automatically set to `false`.
- Added `<meta name="csrf-token">` to dashboard layout (required for the PATCH fetch call).

---

## [v2.2.0] 2026-05-27 — Dashboard UI revamp: light/dark mode + premium aesthetic

### Changed

**Layout (`layouts/dashboard.blade.php`) — full visual refresh**
- Added **light/dark theme system** driven by `data-theme` on `<html>`. Theme tokens (`--bg`, `--bg-card`, `--text-primary`, `--text-secondary`, `--text-muted`, `--border`, `--card-shadow`, etc.) re-resolve per theme.
- **Theme toggle** button in the nav (sun/moon icon, Alpine-controlled). User preference persisted to `localStorage` under key `butler-theme`; falls back to OS preference on first visit.
- Anti-FOUC inline script applies the saved theme before paint, so there's no flash on load.
- **Smooth transitions** (250–300ms ease) on background, color, border, and box-shadow for all themed surfaces.
- **Premium card styles**: increased radius (16px), refined padding, soft light-mode shadows (`0 1px 3px rgba(16,24,40,.04)`), gradient icon tiles for KPI cards (44px rounded squares with soft glow shadows).
- **Gradient palette** added as CSS variables (`--grad-purple`, `--grad-orange`, `--grad-pink`, `--grad-blue`, `--grad-green`, `--grad-yellow`) used for stat-card icon tiles.
- **Typography upgrade**: Inter at weights 300–800, tighter letter-spacing on headings (-.025em), larger page-header h2 on desktop (28px).
- **Mobile-first responsive grid**: `.grid-2` is 1-column under 640px, `.grid-4` is 2×2 until 900px then 4-up. Tables get horizontal scroll on small screens.
- **Theme-aware Chart.js**: on theme switch, `Chart.defaults.color` and `borderColor` are re-pulled from CSS vars and all existing chart instances are `update('none')`-refreshed live.
- All existing class names (`.card`, `.stat-card`, `.grid-*`, `.data-table`, `.badge-*`, `.fund-card`, `.meal-item`, `.empty-state`, `.field-input`, `.btn-save`, `.toggle-row`, `.alert-*`, `.account-row`, `.section-title`, `.page-header`) preserved — all dependent views (spending, nutrition, insights, settings, history, admin) auto-inherit the new theming with **zero changes**.

**Dashboard index (`dashboard/index.blade.php`) — polish pass**
- Time-aware greeting: "Selamat pagi/siang/sore/malam, {name}" with contextual emoji (☀️ / 👋 / 🌙) based on the user's local hour.
- Date chip badge in the header right side.
- "Total Kekayaan" hero card redesigned: gradient icon tile, larger 34px bold figure, decorative blob in the corner, breakdown columns now read clearly in both themes.
- Removed hard-coded light text colours from KPI card values — they now use `--text-primary` and read correctly in light mode.
- Chart grid colour now pulls from `--border` CSS var so chart lines blend correctly in both themes.

### Why
The original dashboard was dark-only with hard-coded light text colors that would be unreadable in light mode. Users on bright-screen mobile (which is the primary use case for a Telegram webview) need a light option. The new design also aligns with modern SaaS dashboards — softer surfaces, gradient accents, clearer hierarchy — while preserving every controller/route/query and every Blade class name used by other views.

---

## [v2.1.4] 2026-05-27 — Total balance on dashboard home

### Added

**Dashboard: Total Kekayaan (net balance) card**
- `DashboardController@index` now computes three totals:
  - `$totalBalance` — sum of all active funds (`is_active = true`, all fund types)
  - `$totalAccountBalance` — spending accounts only (`fund_type = spending_budget`)
  - `$totalSavingsBalance` — savings / emergency / sinking / goal funds
- A prominent gradient card appears at the top of the dashboard home (above the 4-stat grid) showing:
  - Large "Total Kekayaan" figure (all funds combined)
  - Right-aligned breakdown: "Kas & Dompet" (spending accounts) + "Tabungan & Dana" (savings funds)
  - Fund/account count subtitles
- Accounts section footer shows a "Total Kas & Dompet" subtotal row when more than one account exists.
- Why: users had no quick read of their aggregate financial position — they had to mentally add up each account and fund individually.

---

## [v2.1.3] 2026-05-27 — Dashboard visual overhaul + settings page

### Added

**Telegram: `saldo` / `balance` quick command**
- Keywords `saldo`, `balance`, `/saldo`, `/balance`, `cek saldo`, `lihat saldo` now bypass AI and call `handleQueryBalance()` directly.
- Why: fastest path for the most-asked question. AI parse is wasteful for a fixed keyword.

**Telegram: calorie 80% warning in meal confirmation**
- `formatMealConfirmed()` now has three states: 🟢 under 80%, 🟡 80-99% ("Sisa X kcal"), ⚠️ over goal ("Melebihi target X kcal").
- Why: budget warnings existed but calories had no approaching-limit indicator.

**Telegram: behavioral correction feedback**
- `ProcessBehavioralCorrection` now sends a Telegram message after unlearning: _"Oke, aku sudah update cara baca '[subject]'. Ke depannya akan lebih tepat!"_
- Why: soul system learns silently; users couldn't tell whether corrections were registered.

**Dashboard: `/spending`, `/nutrition`, `/insights` routes wired**
- Three fully-built views were missing controller methods and routes. Added all three.
- Nutrition view adapted from non-existent macro fields to Entry model's actual `food_item` / `calories` / `is_calorie_estimated`.
- Why: views existed but were unreachable — dead code.

---

## [v2.1.2] 2026-05-27 — Improvement pass (all improvement table items)

### Added

**Daily summary: month-to-date income + savings**
- `buildSummaryContext()` now receives `$monthIncome` and `$monthSavings` and passes them to the AI as `totals.month_income_idr` and `totals.month_savings_idr`.
- `buildFallbackSummary()` shows them when AI is unavailable.
- Why: income was logged but never surfaced in the summary the user sees every night.

**Streak: 1-day grace period**
- `Streak::updateStreak()` now treats a 1-day gap (missed exactly yesterday) as streak continuation rather than a reset.
- Misses of 2+ consecutive days still reset the streak.
- Why: missing one day — holiday, travel, sick — was breaking long streaks that represent real behavior change.

**Mood logging via Telegram**
- New `handleMoodLog()` handler in `MessageRouter`. Triggered by messages starting with `mood:` or `mood `.
- Parses mood level (great/good/okay/bad/terrible + Indonesian equivalents) and optional energy level (1-5).
- Writes to `mood_logs` table (upsert per day).
- Why: the insights dashboard had a mood chart but there was no Telegram input path — the chart was always empty.

**Telegram: `tagihan` quick command**
- Keywords `tagihan`, `/tagihan`, `list tagihan`, `daftar tagihan` show all active bills with due dates, payment status, and total monthly commitment.
- Why: users had to open the webview to see bills. Common question that deserved a shortcut.

**Telegram: `settings` quick command**
- Keywords `settings`, `/settings`, `pengaturan`, `ubah profil`, `ganti pengaturan` send a 30-minute signed dashboard link.
- Why: no way to reach settings from Telegram without knowing the exact URL.

**Telegram: contextual `help` command**
- `sendHelp()` now appends a live snapshot at the bottom: today's spending, budget remaining, calories, and streak count.
- Why: help was static. Users asking for help are often trying to remember where they stand, not just what commands to use.

**Telegram: [✏️ Edit] button alongside Undo**
- After every confirmed entry, the keyboard now shows `[↩ Undo]  [✏️ Edit]` instead of just `[↩ Undo]`.
- Edit is a signed dashboard URL (30 min). Undo still works exactly as before.
- Added `sendConfirmedWithUndoAndEdit()` to `TelegramService`.
- Why: after the undo window closes, editing required knowing the dashboard URL. Now it's one tap away immediately.

**Behavioral correction dispatch wired**
- `handleAccountSelectionCallback()` now dispatches `ProcessBehavioralCorrection` when the user picks a different account than what behavioral memory suggested.
- Why: the job was defined but never dispatched — the learning-from-correction flow was dead code.

**Dashboard: week-over-week comparison on spending page**
- Spending controller now computes `thisWeekSpend`, `lastWeekSpend`, and `wowChangePct`.
- A banner at the top of the spending view shows the % change with color coding.
- Why: the spending page had historical charts but no simple "am I spending more or less this week?" indicator.

**Admin: AI logs page (`/admin/ai-logs`)**
- New `AdminController@aiLogs` method + view at `/admin/ai-logs`.
- Shows call type, user, intent, confidence, latency, success/fail, input preview, error message.
- Filterable by call type, status, and user.
- Header cards show today's call count, failures, avg latency, avg confidence.
- Why: parse failures and high-latency calls were invisible without direct DB access.

**`EntryService::getMonthSavings()`**
- New query method for month-scoped savings total (mirrors `getMonthIncome()`).

**`MoodLog` model**
- New Eloquent model for `mood_logs` table (uses `telegram_chat_id` as identity).
- Includes `parseMood()` for Indonesian-to-enum mapping.

---

## [v2.1.3] 2026-05-27 — Dashboard visual overhaul + settings page

### Changed

**Layout (`layouts/dashboard.blade.php`) — complete rewrite**
- Added Chart.js 4.4 (CDN) with global dark-theme defaults.
- Added all missing CSS classes referenced by analytics views: `.card` (with colour variants `.red/.green/.orange/.blue/.accent/.yellow`), `.grid-2/3/4`, `.chart-container`, `.progress-bar`, `.data-table`, `.badge-*`, `.meal-item`, `.empty-state`, `.field-input`, `.btn-save`, `.toggle-row`, `.alert-success/error`.
- Full navigation bar: Beranda · Spending · Nutrition · Insights · Riwayat · Settings · Admin.
- Nutrition tab only shows when user is in calorie mode.
- `formatRupiah()` global JS helper available to all pages.

**Dashboard index (`dashboard/index.blade.php`) — complete rewrite**
- 4 stat cards: today's spending with progress bar, monthly spending vs budget, monthly income + savings, calories OR streak (mode-dependent).
- 7-day spending line chart (Chart.js, fills missing days with 0).
- Category breakdown doughnut chart (this month) with top-4 list below.
- Bills due within 7 days callout section.
- Streak cards (shown separately when calorie mode already occupies the 4th slot).
- Accounts & wallets list.
- Sinking funds / goals with progress bars, target dates, expand-to-see-breakdown.
- Recent 6 activities with type badge + amount.
- Controller extended with: todayIncome, monthlySavings, spendingChart (7-day), categoryBreakdown, streak, billsDue.

### Added

**Settings page (`GET/POST /dashboard/settings`)**
- Full form: name, timezone, currency, tracking mode, daily budget, monthly budget, monthly income, calorie goal, daily summary toggle + time.
- Telegram info section (read-only): chat ID, username, member since, onboarding status.
- Validates and saves to user record; redirects with flash success message.

---

## [v2.0] 2026-05-26 — Soul Table architecture (behavioral memory)

- Behavioral memory engine (`behavioral_memory` table, `BehavioralMemoryService`)
- Confidence-based learning: observe → strengthen → consent gate at 0.80 → auto-apply at 1.0
- Undo architecture: 5-minute undo window, fund transaction reversal
- Queue prioritization: high (webhook), medium (balance), low (behavioral + summary)
- Policy engine: explicit_input / auto_apply / soft_confirmation / needs_clarification

---

## [v1.2] 2026-05-23 — Financial structure

- Financial accounts (`accounts` table)
- Bills, debts, sinking funds, income logging
- Fund debit/credit on entry confirmation

---

## [v1.0] 2026-05-17 — MVP

- Telegram webhook + 4-gate state machine
- AI message parsing (14 intents, confidence scoring)
- Expense + meal logging with confirmation keyboard
- Onboarding webview (6-step signed URL flow)
- Daily summary at 9pm
- Streak tracking
- Multi-user (scoped to `telegram_chat_id`)
