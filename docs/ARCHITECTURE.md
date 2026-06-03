# Project Butler — Architecture Reference

> Living document. Updated with every architecture revision.
> Current version: **v2 (2026-06-03)**

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
