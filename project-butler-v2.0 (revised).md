# Project Butler — Reference v2.1

> Revises v2.0: renames soul → behavioral memory, adds policy engine layer,
> formalizes interaction modes, locks tone direction, adds correction metadata.

---

## What Butler Is (Final Definition)

```
An intelligent conversational operating layer for daily life.

NOT: a hyper-emotional AI companion.
NOT: accounting software.
NOT: a spreadsheet bot.
```

Butler should feel:

```
calm · adaptive · lightweight · predictable · efficient
```

Butler should NOT feel:

```
emotionally attached · overly human · hyper-personal · excessively playful
```

The assistant quietly reduces friction over time.
It does not comment on that fact.

---

## What Is Locked

```
Telegram     = operational layer. Log, ask, receive. Nothing else.
Webview      = configuration layer. All setup lives here.
Onboarding   = webview only. Telegram sends one signed URL, one time.
Auth         = telegram_chat_id. No passwords. No registration.
LLM role     = extract, estimate, format responses only.
Backend role = all financial logic, account resolution, behavioral rules.
```

---

## The Four-Layer Interaction Architecture

Every user message passes through exactly these four layers in order.
No layer does another layer's job.

```
User Message
     │
     ▼
┌─────────────┐
│   EXTRACT   │  LLM — pull intent, entities, category, calorie estimate
│   (LLM)    │  Returns: structured JSON, no decisions
└──────┬──────┘
       │
       ▼
┌─────────────┐
│   RESOLVE   │  Backend — determine account, validate, apply learned preferences
│  (Backend)  │  Returns: fully resolved transaction, no LLM involved
└──────┬──────┘
       │
       ▼
┌─────────────┐
│   POLICY    │  Backend — convert behavioral confidence into interaction_mode
│  (Backend)  │  Returns: interaction_mode enum, not a raw score
└──────┬──────┘
       │
       ▼
┌─────────────┐
│   FORMAT    │  LLM — write the response using resolved data + interaction_mode
│   (LLM)    │  Returns: final Telegram message text
└─────────────┘
```

---

## Layer 1 — Extract (LLM)

Responsibilities:
- Parse intent from natural language
- Extract entities (merchant, amount, item, quantity)
- Classify rough category
- Estimate calories from food description
- Normalize Indonesian/mixed language

```json
Input:  "gofood ayam geprek 45k"

Output: {
  "intent": "log_expense",
  "merchant": "GoFood",
  "item": "Ayam Geprek",
  "category": "food",
  "amount": 45000,
  "calories_estimate": 480,
  "calorie_source": "ai_estimate"
}
```

**The LLM must NOT:**
- Choose which account to deduct from
- Apply or evaluate learned preferences
- Make balance decisions
- Perform any business logic

---

## Layer 2 — Resolve (Backend)

Responsibilities:
- Look up learned preferences from behavioral memory
- Apply default account if no preference found
- Apply user-corrected calorie values over AI estimates
- Validate transaction state
- Check undo window conflicts

```php
// Resolve account
$preference = BehavioralMemory::resolve($userId, 'merchant_account', 'GoFood');

if ($preference && $preference->auto_apply) {
    $accountId = $preference->value['account_id'];
    $accountSource = 'learned';
} else {
    $accountId = $user->default_account_id;
    $accountSource = 'default';
}

// Resolve calories — user correction overrides AI
$calorieOverride = BehavioralMemory::resolve($userId, 'food_calories', 'Ayam Geprek');
if ($calorieOverride) {
    $calories = $calorieOverride->value['kcal'];
    $calorieSource = 'user_preference';
} else {
    $calories = $extracted['calories_estimate'];
    $calorieSource = 'ai_estimate';
}
```

Resolved output passed to Policy layer:

```json
{
  "amount": 45000,
  "account_id": 3,
  "account_name": "GoPay",
  "account_source": "learned",
  "calories": 520,
  "calorie_source": "user_preference",
  "behavioral_confidence": 0.91
}
```

---

## Layer 3 — Policy Engine (Backend)

The backend converts `behavioral_confidence` into an `interaction_mode`.
The Format layer never sees a raw confidence score.
This centralizes all UX policy in one place.

```php
function resolveInteractionMode(
    string $accountSource,
    float $confidence,
    bool $autoApply
): string {
    if ($accountSource === 'explicit') return 'explicit_input';
    if ($autoApply && $confidence >= 1.0) return 'auto_apply';
    if ($confidence >= 0.50) return 'soft_confirmation';
    return 'needs_clarification';
}
```

| interaction_mode | Condition | What happens |
|---|---|---|
| `explicit_input` | User stated account in message | Record directly, no question |
| `auto_apply` | `auto_apply=true`, consent given | Apply silently, state it once |
| `soft_confirmation` | confidence 0.50–0.99, no consent yet | Confirm as light question |
| `needs_clarification` | No preference, no default match | Ask which account |

This enum is what gets passed to the Format layer.
Changing UX behavior means changing this function — not touching prompts.

---

## Layer 4 — Format (LLM)

Receives: fully resolved transaction + `interaction_mode`
Writes: the final Telegram message

Prompt structure:

```
SYSTEM:
You are Butler, a calm financial assistant.
Tone: efficient, lightly conversational, non-emotional.
Language: Bahasa Indonesia, casual but not overly familiar.
Never comment on habits. Never use emotional language.
Write short. Never exceed 3 lines.

RESOLVED DATA:
{resolved transaction JSON}

INTERACTION MODE: {interaction_mode}

FORMAT RULES BY MODE:

explicit_input →
  "Siap, dicatat:\n{item} {amount} pakai {account}."

soft_confirmation →
  "Tercatat {amount} buat {item}.\nIni pakai {account} kayak biasanya kan?"

auto_apply →
  "{item} {amount} udah dicatat.\nPakai {account} ya."

needs_clarification →
  "Dari account mana?\n{list accounts as buttons}"
```

---

## Tone — Canonical Examples

### Correct

```
Tercatat 45k buat Ayam Geprek.
Ini pakai GoPay kayak biasanya kan?
```

```
Ayam Geprek 45k udah dicatat.
Pakai GoPay ya.
```

```
Siap, dicatat:
Ayam Geprek 45k pakai GoPay.
```

### Wrong — Never Do This

```
Aku hafal banget kebiasaan kamu 😊
```
*Too emotionally aware. Weakens trust in a financial context.*

```
Tumben banget kamu jajan malam ini hehe
```
*Feels invasive. Butler should not comment on personal behavior.*

```
Wah, pengeluaran kamu hari ini lumayan banyak ya!
```
*Emotional commentary. Not Butler's role.*

The assistant should feel like a calm intelligent operator.
Not an AI best friend.

---

## Behavioral Memory (formerly: Soul Table)

### Internal Naming Convention

| Old (v2.0) | Correct (v2.1) |
|---|---|
| Soul Table | `behavioral_memory` table |
| Soul Engine | Behavioral Engine |
| Soul Confidence | Behavioral Confidence |
| Soul Rule | Learned Preference |

Use the correct names in code, migrations, and variable names.
Butler can be described as "adaptive" or "intelligent" in marketing.
The codebase does not use "soul" anywhere.

---

### Schema

```sql
CREATE TABLE behavioral_memory (
  id                  BIGINT PRIMARY KEY,
  user_id             BIGINT NOT NULL REFERENCES users(id),

  domain              ENUM(
                        'merchant_account',  -- GoFood → GoPay
                        'food_calories',     -- Ayam Geprek → 520kcal (corrected)
                        'food_portion',      -- nasi goreng → avg 320gr
                        'meal_timing',       -- lunch → avg 12:28
                        'category_account',  -- food → GoPay (category fallback)
                        'spend_rhythm'       -- friday → avg Rp180k (v2, needs 4wk data)
                      ) NOT NULL,

  subject             VARCHAR(128) NOT NULL, -- "GoFood", "Ayam Geprek", "lunch"

  value               JSONB NOT NULL,
  /*
    merchant_account:  { account_id: 3, account_name: "GoPay" }
    food_calories:     { kcal: 520, ai_estimate: 480 }
    food_portion:      { avg_grams: 320, sample_count: 7 }
    meal_timing:       { avg_hour: 12, avg_minute: 28, std_dev_min: 18 }
    category_account:  { account_id: 3, account_name: "GoPay" }
    spend_rhythm:      { avg_idr: 180000, observation_weeks: 4 }
  */

  -- Forward-compatible context (do not build now)
  context_json        JSONB DEFAULT '{}',
  -- future: { "hour_from": 6, "hour_to": 12 } for time-of-day rules

  -- Trust
  behavioral_confidence DECIMAL(4,3) DEFAULT 0.100,
  observation_count   INTEGER DEFAULT 1,
  user_consented      BOOLEAN DEFAULT FALSE,
  auto_apply          BOOLEAN DEFAULT FALSE,

  last_observed_at    TIMESTAMP NOT NULL,
  created_at          TIMESTAMP NOT NULL,
  updated_at          TIMESTAMP NOT NULL,

  UNIQUE (user_id, domain, subject, context_json)
);
```

---

### Confidence Rules

```
Each confirmation:        behavioral_confidence += 0.10 (max 1.0)
Each correction:          behavioral_confidence -= 0.30 (min 0.0)
User explicit consent:    behavioral_confidence = 1.0
User explicit denial:     DELETE row

behavioral_confidence < 0.50    → never apply, never suggest
behavioral_confidence 0.50–0.79 → soft_confirmation mode
behavioral_confidence 0.80–0.99 → trigger consent gate (once only)
behavioral_confidence = 1.00    → auto_apply mode
```

---

### Consent Gate (Triggered Once at 0.80)

```
Butler: Aku lihat kamu biasanya pakai GoPay buat GoFood.
        Mau aku otomatisin ke depannya?
        [Boleh] [Jangan]
```

On [Boleh]: `user_consented = true`, `auto_apply = true`, `behavioral_confidence = 1.0`
On [Jangan]: DELETE row. Never ask again for this subject.

Ask at most once per subject.
Track with `consent_asked_at TIMESTAMP NULLABLE` on the row.

---

### Correction Loop (Unlearning)

Triggered when user says "bukan GoPay, cash" or taps Undo then corrects.

```
1. Weaken old preference:
   behavioral_memory WHERE domain='merchant_account' AND subject='GoFood'
   → behavioral_confidence -= 0.30
   → if behavioral_confidence <= 0.0: DELETE

2. Strengthen new preference:
   UPSERT behavioral_memory (domain='merchant_account', subject='GoFood', value.account='Cash')
   → observation_count += 1
   → behavioral_confidence += 0.10

3. Store correction metadata on the entry:
   entries.correction_reason = 'wrong_account'
```

**`correction_reason` enum** (add to `entries` table):

```sql
correction_reason ENUM(
  'wrong_account',
  'wrong_category',
  'wrong_amount',
  'wrong_food_estimate',
  'wrong_merchant',
  'duplicate'
) NULLABLE
```

This field is your learning signal audit trail.
Query it to find which domains the behavioral engine gets wrong most often.

---

### Jobs

```
UpdateBehavioralMemory   → runs after every CONFIRMED entry (queue: low)
ProcessBehavioralCorrection → runs after every correction (queue: low)
```

Never run these synchronously inside the webhook handler.

---

## Undo Architecture

Every confirmed entry gets a 5-minute undo window.

### Schema addition on `entries`

```sql
undo_token          VARCHAR(64) NULLABLE,
undo_expires_at     TIMESTAMP NULLABLE,
is_undone           BOOLEAN DEFAULT FALSE
```

### Confirmation message format

```
Tercatat:
Makanan • Rp45.000
Pakai GoPay

[↩ Undo]
```

The [↩ Undo] button disappears after `undo_expires_at`.
After expiry, entry is permanent (soft-delete still available via dashboard).

On undo:
1. Validate `undo_expires_at > NOW()`
2. `is_undone = true`, `deleted_at = NOW()`
3. Reverse account balance
4. Do NOT reverse behavioral memory updates
5. Reply: "Oke, dibatalin."

No "Mau catat ulang?" — keep it short. User will log again if they want to.

---

## Daily Summary Philosophy

Summaries should feel: useful, observant, non-invasive.

**Good:**
```
Hari ini pengeluaran kamu Rp100.000.
Sebagian besar buat makanan dan transport.
Budget sisa: Rp50.000 💚
```

**Acceptable light observation:**
```
Hari ini kamu lebih sering jajan sore dibanding biasanya.
```

**Never:**
```
Wah hari ini kamu boros banget ya!
Tumben jarang jajan malam ini 😄
Aku notice kamu selalu beli kopi tiap pagi, lucu deh!
```

The summary observes. It does not editorialize.
One behavioral note per summary maximum. Only if relevant and non-judgmental.

---

## Queue Priority

```
HIGH     Telegram webhook handler (parse + format response)
MEDIUM   Account balance update after confirmed entry
LOW      UpdateBehavioralMemory job
LOW      ProcessBehavioralCorrection job
LOW      Daily summary generation
LOWEST   Spend rhythm aggregation, analytics
```

```php
// Laravel queue dispatch
ProcessWebhook::dispatch($payload)->onQueue('high');
UpdateAccountBalance::dispatch($entry)->onQueue('default');
UpdateBehavioralMemory::dispatch($entry)->onQueue('low');
SendDailySummary::dispatch($user)->onQueue('low');
```

Never let a behavioral or summary job block a chat response.

---

## Full Parsing Prompt v2.1

```
SYSTEM:
You are Butler's extraction engine.
Return ONLY valid JSON. No explanation. No markdown.

USER CONTEXT (behavioral memory — do not override unless message says otherwise):
{soul_context: food_calories corrections + food_portion averages + meal_timing}

EXTRACT FROM MESSAGE:
{
  "intent": "log_expense|log_meal|log_income|log_saving|query|set_reminder|unknown",
  "data": {
    "amount": integer or null,
    "currency": "IDR",
    "merchant": "string or null",
    "category": "food_drink|transport|shopping|entertainment|health|utilities|education|other",
    "item": "string or null",
    "quantity_grams": integer or null,
    "calories_estimate": integer or null,
    "calorie_source": "user_preference|ai_estimate|unknown",
    "note": "string or null",
    "entry_time": "ISO8601 or null"
  },
  "confidence": 0.0–1.0,
  "needs_clarification": boolean,
  "clarification_question": "string or null"
}

USER MESSAGE: "{message}"
```

Note: `account_id` is NOT in the extract output.
Account resolution happens in the Resolve layer, not here.

---

## Database Tables Summary v2.1

### New
| Table | Purpose |
|---|---|
| `behavioral_memory` | Learned preferences, calorie corrections, meal timing |
| `accounts` | Real money locations (BCA, GoPay, Cash) |

### Updated
| Table | Changes |
|---|---|
| `entries` | + `account_id`, `undo_token`, `undo_expires_at`, `is_undone`, `correction_reason` |
| `users` | + `default_account_id` FK → accounts |

### Unchanged
`streaks`, `daily_summaries`, `ai_logs`, `bills`, `debts`, `funds`, `reminders`

---

## MVP Checklist (Final)

```
✓ Webview onboarding — profile, accounts, budget, health, notifications
✓ Accounts table + default account
✓ Four-layer pipeline: extract → resolve → policy → format
✓ behavioral_memory table + UpdateBehavioralMemory job
✓ Consent gate at confidence 0.80
✓ Correction loop + correction_reason field
✓ Undo button (5-minute window)
✓ Expense logging — natural language, account auto-resolved
✓ Calorie estimation with behavioral memory override
✓ Daily summary — observant, non-invasive, under 90 words
✓ Queue priority — high/default/low separation
```

## Not in MVP
```
✗ context_json time-of-day rules on behavioral_memory
✗ spend_rhythm domain (needs 4+ weeks real data)
✗ Bills dashboard setup (logging works, setup = webview v2)
✗ Sinking funds / financial goals
✗ Debt engine
✗ OCR receipts, voice logging, iOS client
```

---

*Last updated: May 2026 — v2.1 Behavioral Engine + Policy Layer + Tone Lock*
