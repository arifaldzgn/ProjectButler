# Project Butler — Master Reference Document

> Personal AI assistant for daily tracking via Telegram.  
> Stack: Laravel · Telegram Bot API · Claude API · PostgreSQL  
> Scope: Expenses · Calories · Savings · Reminders  

---

## What Butler Is and Isn't

**Butler is:**
- A conversational data capture layer with an intelligence engine on top
- A single-surface assistant that lives where the user already is (Telegram)
- A frictionless logger that turns natural language into structured data
- A proactive summarizer that gives context, not just numbers

**Butler is not:**
- A form-filling app with chat UI
- A replacement for a full finance app (Wallet, YNAB)
- A calorie counting app with chat support
- A complex multi-feature platform

**The core loop (never break this):**
```
User sends message → AI parses intent + entities → Saved to DB
                                                         ↓
                    9pm: DB entries → AI summary → Delivered to user
```

---

## Structure

### 1. Telegram Bot (sole input surface for MVP)
- Receives all user input as natural text
- Sends confirmations via inline keyboards
- Delivers scheduled summaries and reminders
- No commands beyond `/start` and `/summary` for MVP

### 2. AI Pipeline (two distinct prompts)
- **Prompt A — Parser**: deterministic, returns JSON, no personality
- **Prompt B — Summary Generator**: generative, returns natural language, all personality
- Never mix these two. Different jobs, different prompts, different calls.

### 3. Database (single source of truth)
- All entries scoped to `user_id` — non-negotiable
- Store raw AI input always, for debugging and future training
- Store AI confidence on every parsed entry
- JSON `metadata` column for domain-specific fields without schema migrations

### 4. Scheduler (background intelligence)
- Daily summary at 21:00 user's timezone
- Behavior-based nudges (no log today, re-engagement after 2 days)
- Pattern detection is v2 — do not build it now

---

## Do's

**Product**
- DO support free-text input as the primary interface — no forced commands
- DO send an inline keyboard after every parsed entry (confirm / edit / cancel)
- DO include a confidence score in every AI parsing call and act on it
- DO store the user's original raw message alongside every entry
- DO give Butler a consistent voice — casual Bahasa Indonesia, non-judgmental
- DO keep the daily summary under 90 words
- DO end every summary with budget/calorie status + a streak line
- DO handle the "no entries today" case — send a re-engagement message, not silence
- DO scope every single DB query to `user_id`

**Technical**
- DO use webhooks, not polling, for production
- DO process AI calls asynchronously via Laravel Queues (Telegram needs fast response)
- DO store `last_summary_sent_at` per user to prevent duplicate sends
- DO set `max_tokens: 250` on every parsing call (enough, prevents cost waste)
- DO log `[user_id, intent, confidence, latency_ms, tokens_used]` for every AI call
- DO enable Bot Privacy Mode in BotFather
- DO store `entry_time` (when it happened) separately from `created_at` (when logged)
- DO use Laravel's timezone-aware scheduling — never assume server timezone = user timezone

**Schema**
- DO use a `metadata` JSONB column for domain-specific fields
- DO use soft deletes (`deleted_at`) on entries — users say "batalin yang tadi"
- DO create a separate `ai_logs` table — not mixed into entries
- DO version your AI prompts (store which prompt version generated each entry)

---

## Don'ts

**Product**
- DON'T build the dashboard yet it's a display problem, not a core problem
- DON'T add `/add_expense`, `/log_meal` commands — that's form-filling, not conversation
- DON'T skip the inline keyboard confirmation — it's how the user corrects mistakes
- DON'T send a generic error message — always give the user a path forward
- DON'T build pattern-based reminders yet — you need 2+ weeks of real data first
- DON'T ask more than 3 questions during onboarding — users abandon
- DON'T use one AI prompt for both parsing and summary generation
- DON'T guess calories without flagging it as an estimate — be honest with the user

**Technical**
- DON'T log raw message content to stdout or files — it's sensitive data
- DON'T retry failed Telegram sends more than twice
- DON'T run AI calls synchronously inside the webhook handler
- DON'T build a separate auth system — `telegram_chat_id` is your auth
- DON'T optimize for scale before you have 10 real users
- DON'T use a single cron at server time for summaries — timezone-aware scheduling only
- DON'T hardcode IDR formatting — normalize currency at parse time

**Schema**
- DON'T create separate tables for meal-specific fields and expense-specific fields yet — use `metadata`
- DON'T delete entries hard — always soft delete
- DON'T store calculated values (totals, averages) in the DB — compute them at query time for MVP

---

## MVP Checklist — First Realization

This is the minimum that makes Butler real. Every box must be checked before adding anything else.

### Infrastructure
- [ ] Laravel project with `.env` configured
- [ ] Telegram webhook registered and receiving messages
- [ ] HTTPS endpoint live (Railway / Render / ngrok for local)
- [ ] Claude API connected and returning parsed JSON
- [ ] Laravel Queue worker running (database driver is fine for MVP)

### Onboarding
- [ ] `/start` triggers onboarding flow
- [ ] Bot asks for name → stores in users table
- [ ] Bot asks for daily budget (IDR) → stores, with a skip option
- [ ] Bot asks for daily calorie goal → stores, with a skip option
- [ ] `onboarding_complete` flag set → user enters main loop
- [ ] Returning users skip onboarding entirely

### Expense Logging
- [ ] Free-text expense message → AI parses → returns JSON with confidence
- [ ] If confidence ≥ 0.75 → send confirmation with inline keyboard
- [ ] If confidence < 0.75 → ask clarifying question
- [ ] User confirms → saved to entries table
- [ ] User cancels → nothing saved, clean exit message
- [ ] User taps Edit → prompt them to retype (keep it simple)

### Calorie Logging
- [ ] Free-text meal message → AI parses food + quantity → estimates calories
- [ ] Confidence score included
- [ ] Clearly marked as estimate if not from a known source
- [ ] Confirmed and saved the same way as expenses

### Daily Summary
- [ ] Scheduled job runs daily, timezone-aware
- [ ] Fetches all entries for the user for today
- [ ] If zero entries → sends re-engagement nudge (not full summary)
- [ ] If entries exist → passes to AI summary generator
- [ ] Summary delivered via Telegram at 21:00 user timezone
- [ ] `last_summary_sent_at` updated after delivery
- [ ] No duplicate sends

### Multi-user
- [ ] All queries scoped to `user_id`
- [ ] New user auto-created on first `/start` message
- [ ] Each user has independent entries, budget, and summary schedule

---

## Onboarding Flow

**Trigger:** User sends `/start` for the first time.

```
Butler: Halo! Aku Butler, asisten harianmu.
        Aku bisa bantu catat pengeluaran, kalori, dan 
        tabungan kamu lewat chat biasa.

        Boleh kenalan dulu? Nama kamu siapa?

User:   Andi

Butler: Hai Andi! 
        Mau set budget harian? Ini buat aku bisa kasih 
        tahu kalau kamu udah mendekati limit.
        (Ketik nominalnya, atau ketik "skip")

User:   200000

Butler: Oke, budget Rp 200.000/hari.
        Kalau gym atau diet — mau set target kalori harian?
        (Ketik nominalnya, atau ketik "skip")

User:   skip

Butler: Siap! Sekarang coba log sesuatu.
        Contoh: "makan nasi goreng 35k" atau "grab 23rb"
```

**State machine:**

| Step | Value stored | Next trigger |
|---|---|---|
| `new` | — | User sends `/start` |
| `asked_name` | `name` | User replies any text |
| `asked_budget` | `daily_budget_idr` or null | User replies number or "skip" |
| `asked_calorie` | `daily_calorie_goal` or null | User replies number or "skip" |
| `complete` | `onboarding_complete_at` | All future messages → main parser |

**Rules:**
- Never ask more than these 3 questions
- "Skip" always works — do not force goal-setting
- Returning users (existing `telegram_chat_id`) bypass onboarding entirely
- If user sends a message mid-onboarding that looks like an expense, finish onboarding first

---

## Database Schema

### `users`

```sql
id                      BIGINT PK
telegram_chat_id        BIGINT UNIQUE NOT NULL      -- primary identifier, also = auth
telegram_username       VARCHAR(64) NULLABLE        -- @handle, may be null
name                    VARCHAR(128) NOT NULL
timezone                VARCHAR(64) DEFAULT 'Asia/Jakarta'
preferred_language      ENUM('id','en') DEFAULT 'id'

-- Goals (all nullable — user may skip)
daily_budget_idr        INTEGER NULLABLE
daily_calorie_goal      INTEGER NULLABLE

-- Onboarding
onboarding_step         ENUM('new','asked_name','asked_budget','asked_calorie','complete')
onboarding_complete_at  TIMESTAMP NULLABLE

-- Activity tracking
last_active_at          TIMESTAMP NULLABLE
last_summary_sent_at    TIMESTAMP NULLABLE          -- prevents duplicate sends

created_at              TIMESTAMP
updated_at              TIMESTAMP
```

---

### `entries`

The core table. Every logged event — expense, meal, saving — lives here.

```sql
id              BIGINT PK
user_id         BIGINT FK → users.id

type            ENUM('expense','meal','saving') NOT NULL

-- Financials (expenses and savings)
amount          INTEGER NULLABLE                    -- always in IDR, cents-free
currency        CHAR(3) DEFAULT 'IDR'

-- Expense classification
category        VARCHAR(32) NULLABLE                -- see category taxonomy below
merchant        VARCHAR(128) NULLABLE               -- "GoFood", "Grab", "Alfamart"

-- Meal specific
food_item       VARCHAR(256) NULLABLE               -- "nasi goreng", "ayam bakar"
calories        INTEGER NULLABLE                    -- estimated kcal
is_calorie_estimated BOOLEAN DEFAULT TRUE           -- flag if AI estimated, not from DB

-- Shared
note            TEXT NULLABLE                       -- user's optional note
entry_time      TIMESTAMP NOT NULL                  -- WHEN it happened (user context)
metadata        JSONB DEFAULT '{}'                  -- flexible domain-specific fields

-- AI provenance
ai_raw_input    TEXT NOT NULL                       -- original message, always stored
ai_intent       VARCHAR(32) NOT NULL                -- what AI classified this as
ai_confidence   DECIMAL(4,3) NOT NULL               -- 0.000 to 1.000
ai_prompt_version VARCHAR(16) DEFAULT 'v1'          -- which prompt generated this

-- Lifecycle
confirmed_at    TIMESTAMP NULLABLE                  -- null = pending confirmation
deleted_at      TIMESTAMP NULLABLE                  -- soft delete
created_at      TIMESTAMP
updated_at      TIMESTAMP
```

**Category taxonomy (expense):**

| Slug | Label | Common merchants |
|---|---|---|
| `food_drink` | Makan & Minum | GoFood, GrabFood, warteg, kopi |
| `transport` | Transportasi | Grab, Gojek, bensin, tol, parkir |
| `shopping` | Belanja | Tokopedia, Shopee, Alfamart |
| `entertainment` | Hiburan | Netflix, Steam, bioskop |
| `health` | Kesehatan | apotek, gym, dokter |
| `utilities` | Tagihan | listrik, Telkomsel, internet |
| `education` | Pendidikan | Udemy, buku, kursus |
| `other` | Lainnya | fallback |

---

### `streaks`

Tracks consecutive logging behavior per user. Drives the summary's motivational line.

```sql
id              BIGINT PK
user_id         BIGINT FK → users.id UNIQUE         -- one streak record per user

-- Daily logging streak (logged anything today)
log_current     INTEGER DEFAULT 0
log_longest     INTEGER DEFAULT 0
log_last_date   DATE NULLABLE                       -- last date a log was made

-- Expense-specific streak
expense_current INTEGER DEFAULT 0
expense_longest INTEGER DEFAULT 0
expense_last_date DATE NULLABLE

-- Meal-specific streak (for future calorie focus)
meal_current    INTEGER DEFAULT 0
meal_longest    INTEGER DEFAULT 0
meal_last_date  DATE NULLABLE

updated_at      TIMESTAMP
```

**Update logic (run after every confirmed entry):**
```
today = current date in user's timezone
if last_date == today → no change (already logged today)
if last_date == yesterday → current_streak += 1
if last_date < yesterday → current_streak = 1   (streak broken)
if current_streak > longest_streak → longest_streak = current_streak
last_date = today
```

---

### `reminders`

Stores all reminder rules per user. MVP supports time-based and behavior-based only.

```sql
id                  BIGINT PK
user_id             BIGINT FK → users.id

type                ENUM('time_based','behavior_based') NOT NULL
                    -- 'pattern_based' reserved for v2, don't build yet

-- Time-based fields
trigger_time        TIME NULLABLE                   -- e.g. 19:00:00 for "gym at 7pm"
trigger_days        VARCHAR(32) DEFAULT 'mon,tue,wed,thu,fri,sat,sun'
                    -- comma-separated day codes

-- Behavior-based fields
trigger_condition   JSONB NULLABLE
-- Examples:
-- {"type": "no_expense_log", "by_time": "20:00"}
-- {"type": "no_meal_log", "by_time": "13:00"}
-- {"type": "inactive_days", "threshold": 2}

-- Message
message_template    TEXT NOT NULL                   -- the reminder text to send
                    -- supports {name}, {streak}, {budget_remaining}

-- State
is_active           BOOLEAN DEFAULT TRUE
last_triggered_at   TIMESTAMP NULLABLE
trigger_count       INTEGER DEFAULT 0               -- how many times it has fired

created_at          TIMESTAMP
updated_at          TIMESTAMP
deleted_at          TIMESTAMP NULLABLE
```

**Default reminders created during onboarding:**

```
type: behavior_based
trigger_condition: {"type": "no_expense_log", "by_time": "20:00"}
message: "Hei {name}, belum ada catatan pengeluaran hari ini. 
          Ada yang kelewat? Coba ketik sekarang sebelum lupa."
```

```
type: behavior_based
trigger_condition: {"type": "inactive_days", "threshold": 2}
message: "Butler kangen, {name}. Udah 2 hari nggak ada catatan. 
          Gimana kabar keuangan kamu?"
```

---

### `daily_summaries`

Stores each generated summary for audit, debugging, and future analytics.

```sql
id                  BIGINT PK
user_id             BIGINT FK → users.id

summary_date        DATE NOT NULL
summary_type        ENUM('daily','weekly') DEFAULT 'daily'

-- Snapshot of data at time of generation
total_spent_idr     INTEGER DEFAULT 0
total_calories      INTEGER DEFAULT 0
total_saved_idr     INTEGER DEFAULT 0
entry_count         INTEGER DEFAULT 0
budget_remaining    INTEGER NULLABLE                -- null if no budget set
streak_at_time      INTEGER DEFAULT 0              -- streak count when sent

-- AI output
ai_generated_text   TEXT NOT NULL                  -- the actual message sent
ai_prompt_version   VARCHAR(16) DEFAULT 'v1'

-- Delivery
was_delivered       BOOLEAN DEFAULT FALSE
delivered_at        TIMESTAMP NULLABLE
delivery_error      TEXT NULLABLE                  -- log Telegram errors here

created_at          TIMESTAMP

UNIQUE (user_id, summary_date, summary_type)       -- prevents duplicates
```

---

### `ai_logs`

Every AI call logged separately. Critical for debugging, cost tracking, and prompt iteration.

```sql
id                  BIGINT PK
user_id             BIGINT FK → users.id NULLABLE   -- null for system calls
entry_id            BIGINT FK → entries.id NULLABLE -- link if call created an entry

call_type           ENUM('parse','summary','clarification') NOT NULL
prompt_version      VARCHAR(16) NOT NULL

-- Input
raw_input           TEXT NOT NULL                  -- what was sent to AI
token_count_input   INTEGER NULLABLE

-- Output
raw_output          TEXT NOT NULL                  -- full JSON or text response
token_count_output  INTEGER NULLABLE
intent_detected     VARCHAR(32) NULLABLE
confidence_score    DECIMAL(4,3) NULLABLE

-- Performance
latency_ms          INTEGER NOT NULL               -- response time in ms
was_successful      BOOLEAN DEFAULT TRUE
error_message       TEXT NULLABLE

created_at          TIMESTAMP
```

---

## Context Butler Needs for Every AI Summary Call

Pass this as the user content in the summary prompt:

```json
{
  "user": {
    "name": "Andi",
    "daily_budget_idr": 200000,
    "daily_calorie_goal": null
  },
  "date": "Senin, 19 Mei 2026",
  "entries": [
    {
      "type": "expense",
      "amount": 45000,
      "category": "food_drink",
      "merchant": "GoFood",
      "entry_time": "12:30"
    },
    {
      "type": "expense",
      "amount": 23000,
      "category": "transport",
      "merchant": "Grab",
      "entry_time": "08:15"
    }
  ],
  "totals": {
    "spent_idr": 68000,
    "budget_remaining_idr": 132000,
    "calories_consumed": null
  },
  "streak": {
    "log_current": 4,
    "log_longest": 7
  },
  "pattern_note": "4 consecutive days with food delivery spending"
}
```

---

## Confidence Score Behavior

| Score | What Butler does |
|---|---|
| ≥ 0.90 | Auto-confirm with inline keyboard (default tap = save) |
| 0.75 – 0.89 | Show confirmation with parsed data, require explicit confirm tap |
| 0.50 – 0.74 | Show what was parsed, highlight uncertain fields, ask user to verify |
| < 0.50 | Don't guess — ask clarifying question, show example format |

---

## Error Response Templates

Never show a technical error. Every failure has a user-facing recovery path.

| Situation | Butler says |
|---|---|
| AI parse fails entirely | "Hmm, Butler nggak nangkep yang ini. Coba format: `50k makan siang` atau `grab 23rb`" |
| Amount not found | "Bayar berapa, {name}? Aku butuh nominalnya dulu." |
| Category unclear | "Ini untuk apa? Makan, transport, atau belanja?" |
| User says "edit tadi" but no recent entry | "Entry mana yang mau diubah? Coba ketik ulang dengan nominal yang benar." |
| Telegram delivery fails | Log error → retry once after 30s → log final failure, do not retry again |
| No entries today at summary time | "Hei {name}, nggak ada catatan hari ini. Coba ketik pengeluaran terakhir kamu sebelum tidur." |

---

## Prompt Versioning Convention

Every prompt has a version string. Store it in `ai_logs.prompt_version` and `entries.ai_prompt_version`.

```
parse_expense_v1    -- initial
parse_expense_v2    -- after fixing IDR format handling
summary_daily_v1    -- initial
```

When you update a prompt, bump the version. This lets you trace which version created which entries and compare quality over time.

---

## What Is Deliberately Out of Scope (v1)

---

*Last updated: May 2026 — MVP phase*
