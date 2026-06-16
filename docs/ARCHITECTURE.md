# Project Butler — Architecture Reference

> Living document. Updated with every architecture revision.
> Current version: **v2.6.5 (2026-06-14)**

---

## System Overview

Project Butler is a personal AI assistant with a Telegram-first UX, extended to support
iPhone Shortcuts, Android, web, and desktop clients via a secure REST API.

The **Telegram Bot Token is server-side only** — clients authenticate with per-device
Sanctum tokens issued through a self-service pairing flow.

---

## Architecture Diagram (v2)

```
┌─────────────────────────────────────────────────────────────────┐
│                          CLIENTS                                │
│  Telegram │ iPhone Shortcut │ Android │ Web │ Desktop │ Raycast │
└─────────────────────┬───────────────────────────────────────────┘
                      │
         ┌────────────┴────────────┐
         │   ENTRY POINTS          │
         │                         │
         │  Telegram Webhook        │   Server-to-server
         │  POST /api/telegram/     │   No auth
         │  webhook                 │
         │                         │
         │  Shortcut API            │   Bearer token auth
         │  POST /api/shortcut/     │   Sanctum
         │  message                 │
         └────────────┬────────────┘
                      │
         ┌────────────▼────────────┐
         │   QUEUE WORKER          │
         │                         │
         │  ProcessTelegramMessage  │   Queue: high
         │  job                     │
         │  ├─ Parses tag format    │
         │  ├─ Fires MessageReceived│
         │  └─ Calls MessageRouter  │
         └────────────┬────────────┘
                      │
         ┌────────────▼────────────┐
         │   MESSAGEROUTER         │   ← SOURCE OF TRUTH
         │   (unchanged)           │
         │                         │
         │  ├─ Onboarding gate     │
         │  ├─ Quick commands      │
         │  ├─ AI parse            │
         │  ├─ Intent routing      │
         │  └─ Domain service calls│
         └────────────┬────────────┘
                      │
         ┌────────────▼────────────┐
         │   DOMAIN SERVICES       │
         │                         │
         │  EntryService           │
         │  FundService            │
         │  BillService            │
         │  DebtService            │
         │  ReminderService        │
         │  StreakService           │
         │  FinanceReviewService   │  ← v2.6: self-contained review
         └────────────┬────────────┘
                      │
         ┌────────────▼────────────┐
         │   DELIVERY              │
         │                         │
         │  TelegramService         │   → Telegram Bot API
         │  (response capture)      │     (token hidden)
         │                         │
         │  ShortcutMessageService  │   → Cache relay
         │  (relayResponse)         │     → HTTP response
         └─────────────────────────┘
```

---

## Extension Points (Phase 2 Hooks)

These exist today as contracts. Phase 2 will wire them into `ProcessTelegramMessage`.

```
app/
  Adapters/
    ChannelAdapterInterface.php    ← contract for all channels
    TelegramAdapter.php            ← thin stub wrapping TelegramService
    ShortcutAdapter.php            ← relay via ShortcutMessageService
  Pipeline/
    MessageContext.php             ← immutable input DTO
    ResponsePayload.php            ← channel-agnostic output DTO
```

### Phase 2 Migration Path

When ready to fully decouple Telegram from `MessageRouter`:

1. Make `MessageRouter::handle()` return `ResponsePayload` instead of `void`
2. Remove direct `TelegramService` calls from `MessageRouter`
3. In `ProcessTelegramMessage::handle()`, dispatch the `ResponsePayload` to the
   appropriate `ChannelAdapterInterface` implementation
4. `TelegramAdapter::send()` calls `TelegramService::sendMessage()` directly
5. `ShortcutAdapter::send()` calls `ShortcutMessageService::relayResponse()`

**Zero new infrastructure required.** Adapters already exist.

---

## Database Schema (v2)

### Core (existing)
```
users                   — user profiles, settings, onboarding state
entries                 — financial + calorie log entries
funds / fund_transactions
accounts
bills / debts
categories
streaks
reminders
ai_logs
behavioral_memories
daily_summaries
personal_access_tokens  — Sanctum tokens
```

### v2 Additions
```
devices                 — registered devices per user
  id, user_id, name, platform, token_id→personal_access_tokens,
  is_active, last_used_at, metadata

pairing_codes           — one-time self-service device pairing
  id, user_id, code, device_name, platform, expires_at, claimed_at, device_id

shortcut_messages       — API submissions from all non-Telegram clients
  id, user_id, message, source, response, status, metadata, processed_at

conversations           — cross-channel conversation threads
  id, user_id, channel, channel_id, title, status, metadata

conversation_messages   — individual turns in a conversation
  id, conversation_id, role, content, intent, confidence,
  ai_latency_ms, shortcut_message_id, metadata

daily_analytics         — aggregated per-user/channel/day metrics
  id, user_id, date, channel, requests_count, intents_distribution,
  total_ai_latency_ms, errors_count
```

### v2.6.1 Additions (no new tables)
All changes are behavioral / in-memory:
- `behavioral_memories` gains a new domain `transfer_source` (`DOMAIN_TRANSFER_SOURCE`)  
  — stores which fund the user habitually uses as a transfer source (subject = `default`).  
  Confidence builds over repeated picks; ≥ 0.50 is auto-applied on ambiguous transfers.

### v2.6 Additions
```
finance_review_profiles  — one review profile per user
  id, user_id (unique)

  -- Step 1: Profil & Pendapatan
  domisili, domisili_key, gaji_bersih

  -- Step 2: Tempat Tinggal
  housing_status (enum: kpr|sewa|kontrak|ortu|lainnya), housing_cost

  -- Step 3: Kebutuhan Makan
  cook_percentage, food_base_monthly,
  hangout_frequency (enum: jarang|1-2x|3-4x|setiap-hari)

  -- Step 4: Transportasi
  transport_type, commute_km_daily, transport_monthly

  -- Step 5: Tagihan & Langganan (JSON snapshots — preserved across re-visits)
  bills_snapshot    [{id, name, amount, included}]
  recurring_snapshot[{id, name, amount, included}]

  -- Step 6: Tanggungan & Gaya Hidup
  tanggungan_count, family_remittance, rokok_monthly,
  gym_monthly, asuransi_monthly, mudik_annual

  -- Step 7: Cicilan & Hutang
  debts_snapshot [{id, name, amount, included}]

  -- Progress & cached output
  last_completed_step (0–7), review_completed_at,
  ai_insights (text, cached), insights_generated_at
```

---

## API Reference (v2)

### Public Endpoints (no auth)
| Method | Path | Description |
|---|---|---|
| `POST` | `/api/telegram/webhook` | Telegram server callback |
| `POST` | `/api/pair/claim` | Claim pairing code → get device token |

### Admin-Secret Endpoints
| Method | Path | Description |
|---|---|---|
| `POST` | `/api/shortcut/token` | Issue Sanctum token directly |
| `POST` | `/api/pair/request` | Generate pairing code for user |

### Authenticated (Bearer token)
| Method | Path | Description |
|---|---|---|
| `POST` | `/api/shortcut/message` | Send message from any client |
| `GET` | `/api/shortcut/status/{id}` | Poll message processing status |
| `DELETE` | `/api/shortcut/token/{id}` | Revoke a token |
| `GET` | `/api/devices` | List registered devices |
| `PATCH` | `/api/devices/{id}` | Rename device |
| `DELETE` | `/api/devices/{id}` | Revoke device |
| `GET` | `/api/devices/{id}/activity` | Device last_used_at + metadata |

### Dashboard — Finance Review (session auth, `dashboard.session` middleware)
| Method | Path | Description |
|---|---|---|
| `GET` | `/dashboard/finance-review` | Redirect to current wizard step or result |
| `GET` | `/dashboard/finance-review/step/{1-7}` | Show wizard step |
| `POST` | `/dashboard/finance-review/step/{1-7}` | Save step, advance to next |
| `GET` | `/dashboard/finance-review/result` | Finance Review result page |
| `POST` | `/dashboard/finance-review/recalculate` | Refresh AI insights only |
| `POST` | `/dashboard/finance-review/reset` | Delete profile, restart wizard |

### Dashboard — Help (session auth, `dashboard.session` middleware)
| Method | Path | Description |
|---|---|---|
| `GET` | `/dashboard/help` | Full capability guide (v2.6.2), mode-filtered via `CapabilityCatalog` |
| `GET` | `/dashboard/auth/{telegram_id}?next=help` | Signed entry → session → redirect to Help tab |

### Idempotency
Send `Idempotency-Key: <uuid>` header to deduplicate retried requests.
TTL: 10 minutes (configurable via `SHORTCUT_IDEMPOTENCY_TTL`).

---

## Event Architecture

```
MessageReceived        → LogMessageReceived    (conversation log, low queue)
                       → UpdateDeviceLastUsed  (device activity, low queue)

AiResponseGenerated    → RecordAnalyticsEvent  (daily counters, low queue)

DeviceRegistered       → (extensible, no listener yet)
DeviceRevoked          → (extensible, no listener yet)
ExpenseRecorded        → (extensible, no listener yet)
ReminderCreated        → (extensible, no listener yet)
IntentDetected         → (extensible, no listener yet)
```

All listeners run async on the `low` queue — they never block the `high` queue
that handles message routing.

---

## Inline Entry Editing (v2.6.5)

Estimates and fat-fingered amounts must be trivially correctable. After a log, the router
arms an edit context so the user's next short reply edits the entry in place:

```
log → armEditContext() → Cache "edit_ctx:{chatId}" = {meal_id, expense_id}  (TTL = calorie_edit_window_minutes)

next message → Gate 2a handleCalorieCorrection()   (before the AI parser)
  parseEditMoney(msg)   : "600k"|"2jt"|"600 rb"  → IDR        → edit EXPENSE amount
  parseCalorieValue(msg): "600"|"600cal"|"jadi 600 kalori"     → {value, explicit}
       explicit cal        → edit MEAL calories
       bare + meal only    → edit MEAL calories
       bare + meal+expense → ASK  [🔥 N kalori] [💸 Ubah jadi Rp …]  (editpick: callback)
       else                → fall through to normal parsing

  applyCalorieCorrection() : update calories, is_calorie_estimated=false, teach food_calories
  applyAmountCorrection()  : update amount, adjust source fund by the delta (FundService)
```

The bare-number ambiguity after a dual log is resolved by asking, not guessing
(`askEditDisambiguation()`); the amount button scales the bare number to the original's
magnitude (`scaleBareToAmount()`) and shows the concrete value. Because the intercept only
fires on a pure numeric reply, money amounts ("grab 25rb") and new logs are never hijacked.
(Also fixed here: the older `[🔢 Edit Kalori]` button queried a non-existent `status`
column — now uses the `confirmed()` scope.)

---

## Analytical Queries (v2.6.4)

A 16th parser intent, `query_analytics`, answers filtered money/calorie questions
("total gojek bulan ini", "berapa kali grab minggu ini", "income bulan lalu"):

```
parse → query_analytics {metric, entry_type, category, merchant, period}
  → MessageRouter::handleAnalyticsQuery()
       → EntryService::analyticsQuery(user, filters)     ← read-only, Eloquent bindings
            scopes: forUser + confirmed + type + whereBetween(entry_time, periodRange())
            + loose LIKE on merchant/note/food_item and category
            aggregate: sum(amount) | sum(calories for meals) | count | per-day average
       → TelegramService::formatAnalyticsResponse()
```

Distinct from `query_spending` (bare today/month total) — `query_analytics` is selected
whenever a merchant, category, count, calorie, or non-standard period is involved. All
inputs are whitelisted; there is no raw-SQL surface. "week" = last 7 days ending today.

---

## Conversational Memory (v2.6.3)

Butler keeps a short-term memory of recent turns so it can resolve follow-ups and
references instead of parsing each message in isolation. The infra (`ConversationService`,
`conversations` / `conversation_messages` tables) existed since the v2 work but was dormant;
it is now wired into the AI path:

```
ProcessTelegramMessage
  ├─ records USER turn   (async, low queue, via LogMessageReceived listener)
  └─ records BUTLER turn (telegram + shortcut) ← getLastSentText()

MessageRouter::handle()
  └─ buildParserContext(user, chatId)
       └─ buildRecentTurns() → getRecentHistory(telegram, chatId, N, window)
            → sanitizeTurnText()  (strip __shortcut:/__photo: tags + debug footer, truncate 150c)
       └─ recent_turns  ──┐
                          ├─→ AIService::parseMessage()  → "RECENT CONVERSATION (context only)" block
                          └─→ AIService::chat()          → casual back-and-forth context
```

Guardrails: the prompt block is subordinate ("extract intent from the CURRENT message,
never re-log history"), capped (`conversation_turns`, default 6) and time-bounded
(`conversation_window_minutes`, default 30), and the whole feature sits behind a
deploy-free kill-switch `conversation_context_enabled` in `config/butler.php`.

Limitation: history is keyed on the Telegram thread (`telegram` + `chatId`); per-channel
threading for Shortcut is a follow-up.

---

## Help & Discoverability (v2.6.2)

`CapabilityCatalog` is the single source of truth for "what can Butler do?". It
feeds two surfaces, which therefore never drift:

```
CapabilityCatalog::groupsForUser(User)   ← mode-filtered (finance/calorie/all)
  ├─ Telegram → MessageRouter::sendHelp()         (grouped text + live snapshot + button)
  └─ Web      → DashboardController::help()        (/dashboard/help — cards, tap-to-copy)
```

Discovery paths into help:
- Exact keywords: `help`, `bantuan`, `/help`, `/start`.
- `MessageRouter::looksLikeHelpRequest()` — natural phrasings ("butler bisa apa aja",
  "list command", "menu", "fitur apa aja", "what can you do") matched in the
  quick-command gate, before any AI call. Tuned to avoid misfiring on real
  transactions ("menu makan 50k").
- The *Panduan Lengkap* button uses a signed `dashboard.auth` link with a
  whitelisted `?next=help`, so it establishes the session then lands on the Help tab.

Separately, `CommandSuggestionService` (v2.3.0) handles *mistyped / unsupported*
messages with a two-stage "🤔 Maksudmu …?" / "Butler belum bisa itu, coba …" engine
that resolves to the closest supported command.

---

## AI Parser Grounding (v2.6.1)

`MessageRouter::handle()` builds a per-user context object before calling `AIService::parseMessage()`:

```
buildParserContext(User)
  └─ funds[]            → user's real fund/account names (max 15)
  └─ learned_foods{}    → BehavioralMemory food_calories rows (confidence ≥ 0.50)
  └─ mode               → 'finance' | 'calorie' | 'both'
```

The AI is told:
- **Only use names from `funds[]`** for `fund_name`, `source_fund`, `target_fund` — never invent.
- **Calorie values in `learned_foods`** are authoritative (`is_calorie_estimated=false`); skip re-estimation.
- **Mode steering** — finance-only users never see `log_meal`; calorie-only users never see money intents.

---

## Transfer Direction Flow (v2.6.1)

Every `transfer_fund` parse result carries a `direction` field:

| Value | Meaning | Cues (Indonesian) | Effect |
|---|---|---|---|
| `out` | Money left a wallet | "transfer pake", "bayar pake", "kirim dari" | Debit source only |
| `in` | Money arrived in an account | "terima", "masuk ke", "diterima" | Credit target only |
| `internal` | Between own accounts | "pindahkan dari X ke Y" or ambiguous | Debit source + credit target |

**Source resolution for `internal` when source is unknown:**

1. Check `behavioral_memories` for `domain=transfer_source`, `subject=default` (confidence ≥ 0.50).
2. If not yet learned → send inline keyboard of all user funds (`xfer_pick:{entryId}:source:{fundId}`).
3. User's pick is observed into `transfer_source` memory; next transfer won't ask.
4. The main spending account (`spending_budget`) is **never silently assumed** as transfer source.

**Callback prefixes used by transfer flow:**

| Prefix | Handler |
|---|---|
| `xfer_pick:{entryId}:{role}:{fundId}` | `handleTransferPickCallback()` — completes pending transfer after user selects account |
| `acct_sel:{entryId}:{fundId}` | existing account selection (expenses) |
| `fund_src:{entryId}:{fundId}` | existing fund source picker (savings deposits) |

---

## Security Architecture

| Layer | Mechanism |
|---|---|
| Bot Token | Server-side only, in `.env`, never in API responses |
| API Auth | Sanctum Bearer tokens, per-device |
| Token Scopes | `shortcut:send`, `shortcut:read` |
| Rate Limiting | 30 req/min per user (`throttle:shortcut`) |
| Idempotency | Cache-backed, SHA-256 keyed, 10-min TTL |
| Onboarding Gate | `ValidateShortcutRequest` blocks incomplete users |
| Device Revocation | Per-device token deletion, others unaffected |
| Pairing Codes | 6-char, 15-min TTL, single-use |

---

## Queue Architecture

| Queue | Workers | Handles |
|---|---|---|
| `high` | 2+ | `ProcessTelegramMessage`, `ProcessCallbackQuery` |
| `low` | 1 | Event listeners, `UpdateBehavioralMemory`, `NotifySavingsGoalMilestone` |

---

## Future Client Support

All clients use `POST /api/shortcut/message` with `source` field:

| Client | `source` | Notes |
|---|---|---|
| iPhone Shortcut | `iphone_shortcut` | Pairing code flow |
| Android | `android` | HTTP Shortcuts / Tasker |
| Raycast | `raycast` | Extension uses `raycast` source |
| Web | `web` | Sanctum SPA auth |
| Desktop | `desktop` | Shell script / menu bar app |
| Apple Watch | `ios` | Shortcut on Watch |
