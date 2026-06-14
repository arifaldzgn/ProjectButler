# Changelog — Project Butler

Format: `[version] YYYY-MM-DD — what changed and why`

---

## [v2.6.1] 2026-06-14 — AI parser grounding + transfer/receipt fixes

### Added

**Direction-aware fund transfers (`transfer_fund` reworked)**
- The parser now classifies every transfer into a `direction` — `in` (money received into an account), `out` (money sent out from a wallet), or `internal` (between the user's own accounts/buckets) — with cue-based rules and few-shot examples. Source/target must resolve to one of the user's real funds; an unknown place is treated as external (null), not invented.
- `MessageRouter::handleTransfer()` applies only the legs that make sense:
  - `in`  → credit target only ("terima 50k ke BCA")
  - `out` → debit source only ("transfer 50k pake GoPay")
  - `internal` → debit source + credit target ("pindahin 200k dari GoPay ke Tabungan")
- **Source is never silently assumed.** When the paying account isn't stated, Butler consults a new learned memory (`BehavioralMemory::DOMAIN_TRANSFER_SOURCE`, subject `default`) for the account the user usually transfers from. If that's not confident yet, it **asks** ("Dari akun mana ke *BCA*?") via an inline keyboard of the user's real funds, then **remembers** the choice — so after a few transfers it stops asking. This matches the "daily account vs big-transfer account" distinction: the main spending account is no longer assumed to be the transfer source.
- `applyFundEffect()`'s `transfer` case is now direction-aware and prefers resolved fund IDs (`source_fund_id` on the entry, `target_fund_id` in metadata) over name lookups; a self-transfer (same source/target) is rejected.
- New callback `xfer_pick:{entryId}:{role}:{fundId}` completes a pending transfer after the user picks the source/target account.
- Direction-aware Telegram copy: `formatTransferConfirmation()` and a new `formatTransferConfirmed()` render the correct "Dari/Ke" framing per direction (an outgoing transfer never reads like an incoming one).
- Tests: `tests/Unit/TransferMessageTest.php` locks the direction→copy contract (7 cases, no DB).

### Fixed

**Receipt photo scanning was crashing on every successful scan**
- `MessageRouter::handleReceiptPhoto()` called `$this->entryService->createPendingEntry()`, but the injected property is `$this->entries` — there is no `entryService` property. Any receipt that scanned above the 0.4 confidence threshold threw "undefined property" and the user got nothing. Corrected to `$this->entries`.

**`transfer_fund` intent was dead end-to-end**
- The whole transfer stack existed (`EntryService` maps `transfer_fund`→`transfer` and populates `source_fund`/`target_fund` metadata, `applyFundEffect` has a `transfer` case, `TelegramService::formatTransferConfirmation()`, `CommandSuggestionService` lists it) — but two links were missing, so "pindahkan 2jt ke jajan" always fell through to the "bingung" fallback:
  1. `AIService::parseMessage()` validated against a whitelist that **omitted** `transfer_fund`, so the parser's own output was silently coerced to `unknown`.
  2. `MessageRouter::handle()` never routed it (not in `$logIntents`, no `match` arm).
- Added `transfer_fund` to the parser whitelist and to `$logIntents`. Added a `transfer` case to `entryToParsedArray()` so the confirmed message carries amount + source/target funds.

**Parser prompt intent count**
- Header said "14 INTENTS" while listing 15, and both `transfer_fund` and `unknown` were numbered "14". Renumbered to 15 with `unknown` as #15.

### Changed

**AI parser is now grounded in per-user context (was context-blind)**
- `BehavioralMemoryService::getParserContext()` existed since v2.0 but was **never called** — learned food calories never reached the parser. Now wired in.
- New `MessageRouter::buildParserContext(User)` assembles three grounding inputs and passes them through the extended `AIService::parseMessage(..., array $userContext)`:
  - **Real fund names** — the parser matches `fund_name` / `account_name` / `source_fund` / `target_fund` against the user's actual funds instead of guessing free-text strings (and is told not to invent names).
  - **Corrected calories** — foods the user previously corrected (behavioral memory, confidence ≥ 0.5) are injected as authoritative values with `is_calorie_estimated=false`.
  - **Tracking mode** — finance-only users never get `log_meal` (a priced food is a plain expense); calorie-only users never get money intents. Saves tokens and removes a class of mis-parses.
- `OpenRouterClient` and all other call sites are unchanged; `parseMessage`'s new `$userContext` arg is optional and backward-compatible.

### Why
Receipt scanning and fund transfers were both shipped but non-functional — silent dead code reachable by normal user phrasing. Separately, the parser had no knowledge of who it was parsing for: it guessed fund names and re-estimated calories the user had already corrected. Feeding it the user's real funds, learned values, and mode turns the most-used path (message → structured entry) from generic to personalized with no extra AI calls.

---

## [v2.6.0] 2026-06-12 — "Sanggup Ga?" Finance Review Wizard

### Added

**AI Finance Review — guided 7-step wizard + result page**
- `finance_review_profiles` table — stores one review profile per user: demographics, housing, food habits, transport, tagihan snapshots, lifestyle, debt snapshots, wizard progress, cached AI insights.
- `FinanceReviewProfile` model — typed casts for all JSON snapshot columns (`bills_snapshot`, `recurring_snapshot`, `debts_snapshot`), `isComplete()` and `nextStep()` helpers.
- `FinanceReviewService` — all review logic:
  - `getAutoFilledBills()` / `getAutoFilledRecurring()` / `getAutoFilledDebts()` — pull live DB data into pre-checked toggle lists; snapshots are preserved on re-visit so user edits are not overwritten.
  - `getFoodEstimate()` / `getTransportEstimate()` — derive estimates from last 30 days of `entries`.
  - `calculateBreakdown()` — produces labelled monthly cost lines for 6 categories (makan, transport, tempat_tinggal, tagihan, cicilan, gaya_hidup) with amounts and % of gaji.
  - `getTangga()` — 5-level financial ladder (Bertahan Hidup → Stabil → Mulai Menabung → Aman & Proteksi → Bertumbuh), includes level-specific fokus utama, target naik tangga, and 3 concrete action steps.
  - `getRatioCards()` — three deterministic ratio cards: Rasio Kebutuhan Pokok (target <55%), Rasio Tabungan (target ≥20%), Dana Darurat (vs emergency fund balance from `funds` table).
  - `getAlternatifKota()` — cost-of-living delta for up to 6 alternative cities using BPS 2026 UMK data + relative CoL multipliers for 15 Indonesian cities.
  - `generateInsights()` — calls `OpenRouterClient::generateText()` with 600 max_tokens; Indonesian personal finance coach persona, benchmarks, 3 actionable recommendations.
  - `normalizeDomisili()` — maps free-text city input to a normalized key for UMK lookup.
- `FinanceReviewController` — 6 actions: `show` (redirect to current step or result), `step` (GET), `saveStep` (POST, per-step upsert), `result` (full breakdown computation), `recalculate` (AI-only refresh), `reset` (delete profile, restart).
- 7 wizard step views (`resources/views/finance-review/steps/step1–7.blade.php`):
  - Step 1: Profil & Pendapatan — domisili autocomplete, gaji bersih (pre-fills from `users.monthly_income_idr`).
  - Step 2: Tempat Tinggal — 5-option tile selector for housing status; cost field hidden when "Tinggal di Ortu".
  - Step 3: Kebutuhan Makan — cook % slider, makan dasar input, nongkrong frequency (4 options with auto-estimated cost).
  - Step 4: Transportasi — transport type grid, commute km, monthly cost.
  - Step 5: Tagihan & Langganan — auto-filled toggle list from `bills` + `recurring_entries`; reactive total.
  - Step 6: Tanggungan & Gaya Hidup — tanggungan count tiles, kiriman keluarga, rokok, gym, asuransi, mudik (all optional).
  - Step 7: Cicilan & Hutang — auto-filled toggle list from `debts`; empty-state with link to Debt Manager.
- `result.blade.php` — full "Sanggup Ga?" result page with 9 sections:
  1. Hero card: sisa dana/bulan, spending bar, UMK comparison, Gaji vs UMK %.
  2. Tangga Finansial: accordion ladder (5 levels), active level expanded with fokus, target, 3 langkah konkret.
  3. Insight AI — AI narrative with "Perbarui Analisis" button + last-generated timestamp.
  4. Kartu Rasio Keuangan — 3 deterministic cards with Baik/Perlu Perhatian/Bahaya traffic-light badges.
  5. Alternatif Kota (up to 6 cities with sisa-dana delta) + Distribusi Pengeluaran (Chart.js doughnut).
  6. Simulasi "What If?" — pure Alpine.js interactive toggles (masak sendiri, transportasi umum) + range slider (biaya kos); sisa dana updates live with no server round-trip.
  7. Realitas di [Kota] — pemasukan vs gaji ideal nyaman vs UMK with contextual banner.
  8. Two stat cards: Potensi Tabungan Tahunan (sisa × 12), Rasio Pengeluaran.
  9. Edukasi Finansial — contextual accordion (4 topics), disclaimer banner.
- Entry point: "Review Keuangan" link in desktop sidebar and mobile drawer (`fa-magnifying-glass-chart` icon).
- 6 new routes inside `dashboard.session` middleware: `finance-review.{index, step, step.save, result, recalculate, reset}`.

### Changed
- `OpenRouterClient::generateText()` now accepts optional `$maxTokens` parameter (default 300); finance review passes 600.
- `layouts/dashboard.blade.php` — added "Review Keuangan" sidebar link and mobile drawer item.

### Migration added
| File | Purpose |
|---|---|
| `2026_06_12_100001_create_finance_review_profiles_table` | finance_review_profiles — all review fields, progress tracking, AI insights cache |

### Why
Users had daily transaction tracking but no way to see their complete monthly cost picture in one place. "Sanggup Ga?" gives a structured self-assessment: auto-fills known data (bills, debts, recurring), collects missing dimensions (housing, food habits, lifestyle), and produces a city-contextual financial health review with AI narrative, interactive what-if simulations, and actionable education — all without requiring a financial advisor.

---

## [Unreleased] — Architecture v2 (2026-06-03)

### Added

#### Phase 1A — Device-Centric Authentication & Self-Service Pairing
- `devices` table — tracks physical/logical devices per user with platform, last_used_at, is_active, metadata
- `pairing_codes` table — one-time 6-character codes for self-service device onboarding (no admin required)
- `Device` model with `revoke()`, `touchLastUsed()`, `forToken()` helpers
- `PairingCode` model with `generateCode()`, `isUsable()`, `isClaimed()`, `isExpired()` helpers
- `DeviceController` — list, rename, revoke, activity endpoints (`GET|PATCH|DELETE /api/devices`)
- `PairingController` — request code (`POST /api/pair/request`) + claim code (`POST /api/pair/claim`)
- `User::devices()`, `User::activeDevices()`, `User::pairingCodes()` relationships

#### Phase 1B — Idempotency Support
- `IdempotencyMiddleware` — `Idempotency-Key` header support with cache-backed deduplication
  - Short-lived in-flight lock prevents concurrent duplicate requests
  - Only caches 2xx responses (errors are not deduplicated)
  - 10-minute default TTL (`SHORTCUT_IDEMPOTENCY_TTL`)
  - Response headers: `X-Idempotency-Key`, `X-Idempotency-Replayed`

#### Phase 1C — Structured Intent Support
- `ShortcutMessageController` — accepts optional `intent` field in POST body
  - Valid intents: `expense`, `income`, `task`, `note`, `journal`, `health`, `reminder`, `general_chat`
- `ShortcutMessageService::process()` — accepts `intentHint` and `deviceId` parameters
- `MessageContext::toTaggedMessage()` — encodes intent hint into the job tag format (`__shortcut:{id}|intent:{hint}|{message}`)
- `ProcessTelegramMessage` — parses Format B tag (`__shortcut:{id}|intent:{hint}|{message}`)

#### Phase 1D — Extension Points (Adapters & DTOs)
- `ChannelAdapterInterface` — contract for future multi-channel delivery (`channel()`, `send()`, `supportsAsync()`)
- `TelegramAdapter` — thin stub wrapping `TelegramService` (migration hook for Phase 2)
- `ShortcutAdapter` — relays responses to Shortcut API callers via `ShortcutMessageService::relayResponse()`
- `MessageContext` (readonly DTO) — immutable value object carrying user, message, channel, intent hint, device ID
- `ResponsePayload` (readonly DTO) — channel-agnostic AI response with `pending()` and `failed()` factory methods

#### Phase 1E — Conversation Tracking
- `conversations` table — one thread per user/channel/channel_id, status: active|archived
- `conversation_messages` table — immutable message turns (user|assistant|system roles)
- `Conversation` model with `findOrStartFor()` helper
- `ConversationMessage` model with `userTurn()` and `assistantTurn()` factory helpers
- `ConversationService` — `recordUserTurn()`, `recordAssistantTurn()`, `getRecentHistory()`
- `User::conversations()` relationship
- `ProcessTelegramMessage` now records assistant turns after each successful response relay

#### Phase 1F — Domain Events & Listeners
- **Events**: `MessageReceived`, `IntentDetected`, `ExpenseRecorded`, `ReminderCreated`, `AiResponseGenerated`, `DeviceRegistered`, `DeviceRevoked`
- **Listeners** (async, low queue):
  - `LogMessageReceived` → records user turn in `conversation_messages`
  - `UpdateDeviceLastUsed` → updates `devices.last_used_at`
  - `RecordAnalyticsEvent` → increments `daily_analytics` counters
- All listeners implement `ShouldQueue` — events never block the request path

#### Phase 1G — Analytics Layer
- `daily_analytics` table — one row per user/channel/day with request count, intent distribution, AI latency sum, error count
- `DailyAnalytic` model with `avg_ai_latency_ms` computed attribute
- `AnalyticsService::record()` — atomic upsert-based aggregation (safe with concurrent workers)
- `AnalyticsService::getSummary()` — retrieve last N days of aggregates per user
- `User::dailyAnalytics()` relationship

#### Infrastructure
- `ProcessTelegramMessage` now fires `MessageReceived` event and records analytics on every message
- `TelegramService::getLastSentText()` / `clearLastSentText()` — enables response relay without changing `MessageRouter`'s void return type
- `AppServiceProvider` — registers all event→listener mappings and uses configurable rate limit
- New env vars: `SHORTCUT_PAIRING_TTL_MINUTES`, `SHORTCUT_IDEMPOTENCY_TTL`
- New routes: `POST /api/pair/request`, `POST /api/pair/claim`, `GET|PATCH|DELETE /api/devices`, `GET /api/devices/{id}/activity`

### Changed
- `routes/api.php` — Device and Pairing routes added; Idempotency middleware added to Shortcut route group
- `AppServiceProvider::boot()` — event listeners registered; rate limiter reads from config
- `ShortcutMessageController` — now resolves `device_id` from current Sanctum token
- `ShortcutMessageService::process()` — signature extended with `intentHint` and `deviceId`
- `config/butler.php` — `shortcut` block extended with `pairing_ttl_minutes`, `idempotency_ttl_seconds`
- `User` model — added `HasApiTokens` trait, new v2 relationships

### Architecture Principles Applied
- **Additive only** — no existing `MessageRouter` or `TelegramService` business logic was modified
- `MessageRouter` remains the source of truth for all AI routing and domain logic
- Channel adapters are extension points, not functional replacements (Phase 2 migration)
- All new async work runs on the `low` queue — critical path (`high` queue) is unchanged

---
## [v2.5.0] 2026-05-28 — Future features implementation (all phases)

### Added

**Phase 1 — Token Count Tracking (Admin)**
- `OpenRouterClient::executeWithFallback()` now extracts `usage.prompt_tokens` / `usage.completion_tokens` from every OpenRouter response and returns them as `token_usage` in the result.
- `AIService` exposes `getLastTokenUsage(): ?array` property, set after every `parseMessage()`, `generateSummary()`, `generateWeeklySummary()`, and `chat()` call.
- `AiLogService::logParseCall()` and `logSummaryCall()` accept optional `?array $tokenUsage` to populate `token_count_input` / `token_count_output` columns (previously always NULL).
- Admin `/admin/token-usage` page: per-user token breakdown, by-call-type breakdown, cost estimates (Gemini Flash pricing), period filter (7d/30d/all).
- Route: `admin.token-usage.index`, nav entry added to admin sidebar.

**Phase 2 — In-Telegram Calorie Editing**
- Confirmed meal entries now show a `[🔢 Edit Kalori]` inline button (alongside undo/edit).
- Tapping it stores a 5-minute cache key `cal_correction:{chatId}` with the entry ID.
- Next message from user is intercepted by `MessageRouter::handleCalorieCorrection()` — accepts "350", "350 kcal", "350kcal" formats; updates `entries.calories` and calls `BehavioralMemoryService::observe()` for `food_calories` domain.
- Invalid input: re-prompts the user and extends the cache TTL.

**Phase 3A — Financial Distribution View (`/dashboard/distribution`)**
- Doughnut chart: balance distribution by account type (Bank, E-wallet, E-money, Cash, Credit Card).
- Horizontal bar chart: this-month spending by account type.
- Per-account table with balance, % of total, transaction count.
- Nav link added to dashboard layout.

**Phase 3B — Cashflow Health + Growth Chart (`/dashboard/cashflow`)**
- 6-month stacked bar (income vs expense) + net savings line overlay.
- Cumulative savings growth line chart.
- Health score gauge (0-100) using `min(100, (net/income) * 200)`.
- Summary cards: expense ratio, total savings, best month.

**Phase 3C — Daily Cashflow Timeline (`/dashboard/timeline`)**
- Day-by-day transaction list with running balance computed in PHP.
- Date navigation (← today →) with date picker.
- Monthly calendar heatmap with click-to-navigate.
- Color-coded entries: income green, expense red, transfer blue, savings purple.

**Phase 3D — Debt Manager & Bill Tracker (`/dashboard/debts`)**
- Debt progress cards sorted by interest rate (avalanche method).
- Bill calendar grid with paid/unpaid status.
- Summary cards: total debt, monthly obligations, estimated payoff date.
- Debt composition doughnut chart.
- Avalanche strategy tip card.

**Phase 4A — Macro Tracking (protein/carbs/fat)**
- Migration: `protein_g`, `carbs_g`, `fat_g` (nullable decimal 6,1) added to `entries` table.
- `AIService::buildParserPrompt()` extended to extract `protein_g`, `carbs_g`, `fat_g` for `log_meal` and `log_meal_and_expense` intents.
- `EntryService::createPendingEntry()` saves macro fields; `getTodayMacros()` returns summed totals.
- `TelegramService::formatMealConfirmation()` shows macro line: "P:25g · C:45g · L:12g _(est)_"
- Nutrition dashboard: macro doughnut chart + P/C/F progress bars with Chart.js.

**Phase 4B — Weekly Summary Reports**
- `WeeklySummaryService` sends 7-day aggregated summaries every Sunday at 20:00 WIB.
- Includes: total spend vs weekly budget, category breakdown, week-over-week comparison, savings, calorie summary, streak.
- `AIService::generateWeeklySummary()` uses a dedicated prompt optimized for weekly reflection tone.
- Scheduler: `weeklyOn(0, '20:00')` in `routes/console.php`; manual command `butler:weekly-summary`.
- Stored in `daily_summaries` table with `summary_type = 'weekly'`.

**Phase 4C — Savings Goal Progress Notifications**
- `FundService::creditFund()` now calls `checkGoalMilestone()` after every credit.
- Milestones: 25%, 50%, 75%, 100% of `target_amount`.
- `NotifySavingsGoalMilestone` job sends a Telegram notification per milestone, guarded by a 2-year Cache key to prevent re-sends.
- No migration needed (existing `funds.target_amount` + `funds.current_balance`).

**Phase 4D — Custom Categories**
- Migration: `categories` table + `category_id` FK on `entries`.
- `Category` model with `seedDefaults()` (9 default Indonesian categories seeded at onboarding completion).
- `AIService::buildParserPrompt()` injects user's custom category names when available.
- `EntryService::resolveCustomCategoryId()` fuzzy-matches AI category string to user's `Category` rows.
- `CategoryController`: CRUD API — `POST/PATCH/DELETE /dashboard/categories/{id}`.
- Settings page: visual category management UI with emoji icon + inline add/edit modal.

**Phase 4E — Recurring Entry Templates**
- Migration: `recurring_entries` table (description, amount, type, frequency, day_of_week, day_of_month, next_run_at).
- `RecurringEntry` model with `advanceNextRun()` and frequency label helpers.
- `RecurringEntryService::processRecurringEntries()`: checks `next_run_at <= now()`, creates confirmed entry, advances schedule, notifies user.
- Scheduler: hourly. Manual command: `butler:recurring`.

**Phase 4F — Personalized Reminder Timing**
- `ReminderService::getOptimalReminderTime(User $user)`: analyses last 14 days of `entry_time` hours, builds hour histogram, finds low-activity gaps → suggests AM/PM reminder slots.
- Persists histogram to `behavioral_memories` with domain `log_timing`.
- `BehavioralMemory::DOMAIN_LOG_TIMING` constant added.

**Phase 4G — AI-Generated Budget Suggestions**
- `BudgetSuggestionService::sendSuggestion()`: aggregates 30-day spending, applies 50/30/20 rule, calls AI for narrative.
- `AIService::generateBudgetSuggestion()` with a concise coaching prompt (under 120 words, IDR amounts, specific actionable tip).
- Quick commands: `saran budget`, `budget suggestion`, `saran keuangan` — bypass AI parse, trigger suggestion directly.

**Phase 4H — Receipt Photo Scanning**
- Telegram webhook detects `photo` messages and dispatches `ProcessTelegramMessage` with `__photo:{fileId}|caption:{text}`.
- `MessageRouter` intercepts `__photo:` messages in `handleReceiptPhoto()`.
- `ReceiptScanService::extractFromTelegramPhoto()`: downloads photo via `getFile` API, sends base64 image to OpenRouter vision model (`generateVisionJson()`), extracts merchant/total/items/date.
- Extracted data flows through standard `createPendingEntry()` → inline ✅/❌ confirmation buttons.
- `OpenRouterClient::generateVisionJson()` added for multimodal (image + text) requests.

**Phase 4I — Financial Health Score**
- Migration: `financial_health_scores` table (score, components JSON, calculated_at).
- `FinancialHealthModel` with band labels (Excellent/Good/Fair/Needs Work) and color codes.
- `FinancialHealthService::calculate()`: 6 weighted factors — savings rate (25%), emergency fund coverage (20%), DTI ratio (20%), budget adherence (15%), bill consistency (10%), logging streak (10%).
- `getOrCalculate()`: returns cached score if < 24h old, otherwise recalculates.
- Dashboard index: health score card with component breakdown grid.
- Cashflow page: detailed per-factor progress bars with contextual labels.

### Changed
- `DashboardController::settings()` now passes `$userCategories` to the view.
- `DashboardController::index()` computes and passes `$healthData` (from `FinancialHealthService`).
- `DashboardController::cashflow()` computes and passes `$healthData`.
- `OnboardingController::done()` calls `Category::seedDefaults($user)` at onboarding completion.
- `TelegramWebhookController` now handles `photo` message type alongside text.

### Migrations added
| File | Purpose |
|---|---|
| `2026_05_28_100001_add_macros_to_entries` | protein_g, carbs_g, fat_g on entries |
| `2026_05_28_100002_create_categories_table` | categories + category_id FK on entries |
| `2026_05_28_100003_create_recurring_entries_table` | recurring_entries |
| `2026_05_28_100004_create_financial_health_scores_table` | financial_health_scores |

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
