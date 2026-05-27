# Changelog — Project Butler

Format: `[version] YYYY-MM-DD — what changed and why`

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
