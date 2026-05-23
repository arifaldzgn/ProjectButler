# Project Butler — Master Reference v1.2

> Personal AI assistant for daily tracking via Telegram.  
> Stack: Laravel · Telegram Bot API · Claude API · PostgreSQL  
> Scope: Expenses · Calories · Savings · Bills · Sinking Fund · Financial Goals · Income · Debt  
> Version: 1.2 — Financial structure expansion + onboarding refactor

---

## What Changed from v1.1

| Area | v1.1 | v1.2 |
|---|---|---|
| Onboarding | Ask name → budget → calorie goal | Ask mode first → branch into finance or calorie setup |
| Financial model | Expenses + Savings only | Full 6-bucket financial structure |
| Savings deduction | Not implemented | Every expense asks "from which fund?" based on user's setup |
| Reminders | Behavior-based (no log today) | + Setup-incomplete reminders for unfilled fund categories |
| Database | `entries` table only | + `funds`, `fund_transactions`, `bills`, `debts` tables |

---

## The Financial Model (Butler's Money Brain)

Adapted from the Loka Journey Financial Planner structure.  
Every user's money lives in exactly one of these 6 buckets.  
Butler needs to know which bucket a transaction touches.

```
INCOME
  └── flows into →  FREE BALANCE  (uang yang bisa dipakai)
                         │
          ┌──────────────┼──────────────────────┐
          ▼              ▼                       ▼
      SPENDING         BILLS              ALLOCATIONS
   (harian, bebas)  (tagihan tetap)    ┌─ SAVINGS / INVEST
                                       ├─ SINKING FUND
                                       └─ FINANCIAL GOALS
                                       
DEBT (cicilan/utang) — tracked separately, reduces free balance
```

### The 6 Buckets Defined

**1. SPENDING (Pengeluaran Harian)**
- What it is: daily variable expenses — food, transport, shopping, entertainment
- Butler behavior: every `log_expense` entry pulls from this bucket by default
- User sets: monthly spending budget per category (optional)
- Examples: GoFood, Grab, kopi, belanja Alfamart

**2. BILLS (Tagihan Tetap)**
- What it is: recurring fixed monthly expenses, predictable amount
- Butler behavior: user pre-registers bills; Butler reminds before due date; user confirms payment
- User sets: bill name, amount, due date (day of month), deducted from which account
- Examples: kos/kontrakan, internet, Spotify, iCloud, BPJS, gym membership

**3. SAVINGS / INVEST (Tabungan & Investasi)**
- What it is: money set aside short-to-mid term, accessible when needed
- Butler behavior: user logs deposits; balance tracked; user declares which account holds it
- User sets: monthly target contribution (optional)
- Examples: tabungan darurat, deposito, reksa dana

**4. SINKING FUND (Dana Cadangan Terencana)**
- What it is: money saved gradually for a known future big expense
- Butler behavior: user sets goal name + target amount + deadline; Butler tracks progress
- Key distinction from Savings: Sinking Fund has a PURPOSE and a DATE
- User sets: fund name, target amount, target date, monthly contribution
- Examples: mudik lebaran, beli laptop, liburan, servis motor tahunan, pajak kendaraan

**5. FINANCIAL GOALS (Tujuan Keuangan Jangka Panjang)**
- What it is: long-term wealth building with a target and timeline
- Butler behavior: tracks accumulated balance + % toward goal; included in monthly summary
- Key distinction from Sinking Fund: Goals are longer horizon, often asset-based
- User sets: goal name, target amount, target date, current balance
- Examples: dana pensiun, uang muka rumah, dana pendidikan anak, haji

**6. DEBT / CICILAN (Utang & Angsuran)**
- What it is: outstanding liabilities that must be paid monthly
- Butler behavior: tracks total debt, monthly installment, remaining balance; flags overdue
- User sets: debt name, total amount, monthly installment, due date, end date
- Examples: KPR, cicilan motor, paylater, kartu kredit

---

## The "From Which Fund?" Problem (v1.1 Bug Fix)

**The bug:** User logs "bayar kos 1.5jt" → entry saved → but tabungan balance unchanged.

**Why it happens:** Butler doesn't know which fund to deduct from because the user hasn't set up their fund structure.

**The fix:** Every expense has a `source_fund` field. This is determined by:

### Resolution Priority (in order)

1. **Auto-resolve by type:**
   - Bills → always deduct from free balance (not from savings)
   - Sinking Fund payment → deduct from that specific sinking fund
   - Daily spending → deduct from free balance

2. **Category-based default:** 
   - If user has set "makan = from Uang Jajan fund" → auto-apply
   - User configures these defaults in onboarding or via `/settings`

3. **Ask at log time (fallback):**
   - Only when amount is large (> 20% of daily budget) OR category is ambiguous
   - Butler: "Ini dari dana mana? [Uang Jajan] [Tabungan] [Lainnya]"

4. **Never block the log:**
   - If user doesn't respond to the fund question within 10 seconds (inline keyboard),
     save with `source_fund = 'free_balance'` as default
   - Mark entry with `source_fund_confirmed = false` for later reconciliation

---

## Updated Onboarding Flow v1.2

### Phase 0 — Mode Selection (NEW)

```
Butler: Halo! Aku Butler, asisten harian kamu 👋
        Aku bisa bantu dua hal utama:

        💰 Finance Tracker
           Catat pengeluaran, tabungan, tagihan,
           dan pantau kesehatan keuangan kamu

        🥗 Calorie Tracker  
           Catat makan, pantau kalori harian,
           dan bantu kamu konsisten dengan diet

        Mau mulai dari mana?
        [💰 Finance] [🥗 Kalori] [Dua-duanya]
```

**Branch A — Finance Tracker selected:**
```
Butler: Oke, Butler bakal bantu kamu manage keuangan!
        
        Ini yang bisa Butler lakukan:
        • Catat pengeluaran harian (otomatis kategorikan)
        • Pantau tagihan tetap & ingatkan sebelum jatuh tempo
        • Tracking tabungan & sinking fund kamu
        • Ringkasan harian jam 9 malam
        • Ingatkan kalau ada yang belum disetup

        Setup cuma 3 menit. Mulai?
        [Yuk Setup] [Nanti Aja]
```

**Branch B — Calorie Tracker selected:**
```
Butler: Siap, Butler bantu tracking kalori kamu!

        Yang bisa Butler lakukan:
        • Catat makanan pakai bahasa natural
        • Estimasi kalori otomatis
        • Pantau kalori harian vs target kamu
        • Ringkasan makan jam 9 malam

        Berapa target kalori harian kamu?
        (Ketik angkanya, atau "skip" kalau belum tau)
```

**Branch C — Both selected:**
```
Butler: Mantap, full setup! Mulai dari finance dulu ya,
        nanti lanjut kalori.
        [lanjut ke Branch A]
```

---

### Phase 1 — Finance Setup (if Finance selected)

**Step 1: Name**
```
Butler: Nama kamu siapa?
User:   Andi
Butler: Hai Andi!
```

**Step 2: Income (optional but recommended)**
```
Butler: Berapa pemasukan bulanan kamu? (gaji atau total income)
        Ini buat Butler bisa kasih konteks yang lebih relevan.
        (Ketik nominalnya, atau "skip")
User:   5000000
Butler: Oke, income Rp 5.000.000/bulan. Tersimpan 👍
```

**Step 3: Spending budget**
```
Butler: Berapa budget belanja harian kamu?
        Ini buat pantau pengeluaran kamu tiap hari.
        (Contoh: 150000 atau "skip")
User:   150000
```

**Step 4: Fund setup — key new step**
```
Butler: Sekarang, kamu punya dana-dana ini nggak?
        Pilih yang ada (boleh pilih lebih dari satu):

        [💊 Dana Darurat] [🏠 Kos/Cicilan] [✈️ Sinking Fund]
        [📈 Tabungan/Investasi] [💳 Cicilan/Utang] [Skip semua]
```

*For each fund selected, Butler asks a quick follow-up:*

**Dana Darurat:**
```
Butler: Berapa saldo dana darurat kamu sekarang?
        (skip kalau belum ada / belum tau)
```

**Kos/Cicilan (Bills):**
```
Butler: Tagihan tetap bulanan kamu apa aja?
        Contoh: "kos 1.5jt tiap tanggal 1, internet 250rb tiap tanggal 5"
        (Bisa ditambah nanti, skip dulu juga oke)
```

**Sinking Fund:**
```
Butler: Kamu lagi nabung untuk apa? 
        Contoh: "liburan target 3jt bulan Desember"
        (Bisa ditambah nanti)
```

**Cicilan/Utang:**
```
Butler: Ada cicilan aktif? Cicilan apa, berapa per bulan, jatuh tempo tanggal berapa?
        Contoh: "cicilan motor 800rb per bulan, tanggal 10"
```

**Step 5: Completion**
```
Butler: Setup selesai! 🎉

        Ringkasan kamu:
        • Income: Rp 5.000.000/bln
        • Budget harian: Rp 150.000
        • Dana darurat: Rp 3.000.000
        • Bills: kos 1.5jt, internet 250rb
        • Cicilan: motor 800rb/bln

        Sekarang coba catat sesuatu!
        Contoh: "makan siang 35k" atau "grab 23rb"
```

---

### Onboarding State Machine v1.2

| Step | Key stored | Skip allowed? |
|---|---|---|
| `mode_select` | `tracking_mode` (finance/calorie/both) | No |
| `asked_name` | `name` | No |
| `asked_income` | `monthly_income_idr` | Yes |
| `asked_spending_budget` | `daily_budget_idr` | Yes |
| `asked_funds` | fund selections | Yes (skip all) |
| `fund_detail_{n}` | per-fund balances/amounts | Yes each |
| `complete` | `onboarding_complete_at` | — |

---

## Database Schema v1.2

### `users` (updated)

```sql
id                      BIGINT PK
telegram_chat_id        BIGINT UNIQUE NOT NULL
telegram_username       VARCHAR(64) NULLABLE
name                    VARCHAR(128) NOT NULL
timezone                VARCHAR(64) DEFAULT 'Asia/Jakarta'
preferred_language      ENUM('id','en') DEFAULT 'id'

-- Mode
tracking_mode           ENUM('finance','calorie','both') DEFAULT 'both'

-- Income
monthly_income_idr      INTEGER NULLABLE

-- Budgets
daily_budget_idr        INTEGER NULLABLE
daily_calorie_goal      INTEGER NULLABLE

-- Onboarding
onboarding_step         VARCHAR(32) DEFAULT 'mode_select'
onboarding_complete_at  TIMESTAMP NULLABLE

-- Activity
last_active_at          TIMESTAMP NULLABLE
last_summary_sent_at    TIMESTAMP NULLABLE

-- Setup completeness flags (drives setup reminders)
has_emergency_fund      BOOLEAN DEFAULT FALSE
has_bills_setup         BOOLEAN DEFAULT FALSE
has_income_set          BOOLEAN DEFAULT FALSE
has_debt_declared       BOOLEAN DEFAULT FALSE   -- true even if answer is "no debt"

created_at              TIMESTAMP
updated_at              TIMESTAMP
```

---

### `entries` (updated from v1.1)

```sql
id                  BIGINT PK
user_id             BIGINT FK → users.id

type                ENUM('expense','meal','saving','income','bill_payment',
                         'sinking_fund_deposit','goal_deposit','debt_payment') NOT NULL

-- Money
amount              INTEGER NULLABLE            -- IDR, no decimals
currency            CHAR(3) DEFAULT 'IDR'

-- Expense / spending classification
category            VARCHAR(32) NULLABLE        -- see taxonomy
merchant            VARCHAR(128) NULLABLE

-- Source fund (NEW v1.2)
source_fund_id      BIGINT FK → funds.id NULLABLE   -- null = free balance
source_fund_confirmed BOOLEAN DEFAULT TRUE      -- false if auto-assigned without confirmation

-- Meal
food_item           VARCHAR(256) NULLABLE
calories            INTEGER NULLABLE
is_calorie_estimated BOOLEAN DEFAULT TRUE

-- Shared
note                TEXT NULLABLE
entry_time          TIMESTAMP NOT NULL
metadata            JSONB DEFAULT '{}'

-- AI provenance
ai_raw_input        TEXT NOT NULL
ai_intent           VARCHAR(32) NOT NULL
ai_confidence       DECIMAL(4,3) NOT NULL
ai_prompt_version   VARCHAR(16) DEFAULT 'v1'

-- Lifecycle
confirmed_at        TIMESTAMP NULLABLE
deleted_at          TIMESTAMP NULLABLE
created_at          TIMESTAMP
updated_at          TIMESTAMP
```

---

### `funds` (NEW v1.2)

The central table. Every financial bucket a user creates lives here.

```sql
id                  BIGINT PK
user_id             BIGINT FK → users.id

fund_type           ENUM('savings','sinking_fund','financial_goal',
                         'emergency_fund','spending_budget') NOT NULL

name                VARCHAR(128) NOT NULL       -- user-defined: "Dana Darurat", "Nabung Liburan"
description         TEXT NULLABLE

-- Balances
current_balance     INTEGER DEFAULT 0           -- current amount in IDR
initial_balance     INTEGER DEFAULT 0           -- what user declared at setup

-- Goal tracking (for sinking_fund and financial_goal)
target_amount       INTEGER NULLABLE            -- goal target
target_date         DATE NULLABLE               -- deadline
monthly_contribution INTEGER NULLABLE           -- planned monthly top-up

-- Computed helpers (updated by trigger/observer after each transaction)
progress_pct        DECIMAL(5,2) DEFAULT 0.00   -- current_balance / target_amount * 100
months_remaining    INTEGER NULLABLE            -- computed from target_date
on_track            BOOLEAN NULLABLE            -- is monthly contribution enough?

-- Bill linking (for spending_budget type)
is_default_spending BOOLEAN DEFAULT FALSE       -- one fund per user can be default for daily spending

-- Status
is_active           BOOLEAN DEFAULT TRUE
created_at          TIMESTAMP
updated_at          TIMESTAMP
deleted_at          TIMESTAMP NULLABLE

UNIQUE (user_id, name)                          -- no duplicate fund names per user
```

**Default funds auto-created during onboarding:**
```
type: spending_budget, name: "Uang Harian", is_default_spending: true
  → balance = user's declared available spending cash (or 0)
```

---

### `fund_transactions` (NEW v1.2)

Every time money moves into or out of a fund, it's recorded here.  
This is the audit trail. `funds.current_balance` is derived from this.

```sql
id                  BIGINT PK
fund_id             BIGINT FK → funds.id
user_id             BIGINT FK → users.id
entry_id            BIGINT FK → entries.id NULLABLE  -- the triggering log entry

transaction_type    ENUM('deposit','withdrawal','adjustment') NOT NULL
amount              INTEGER NOT NULL            -- always positive; direction from transaction_type
note                TEXT NULLABLE

balance_before      INTEGER NOT NULL            -- snapshot for audit
balance_after       INTEGER NOT NULL            -- snapshot for audit

created_at          TIMESTAMP
```

---

### `bills` (NEW v1.2)

Recurring fixed monthly expenses pre-registered by the user.

```sql
id                  BIGINT PK
user_id             BIGINT FK → users.id

name                VARCHAR(128) NOT NULL       -- "Kos", "Spotify", "BPJS Kesehatan"
amount              INTEGER NOT NULL            -- expected monthly amount
currency            CHAR(3) DEFAULT 'IDR'
category            VARCHAR(32) DEFAULT 'bills'

due_day             TINYINT NOT NULL            -- day of month (1-31)
due_day_flexibility TINYINT DEFAULT 0          -- tolerance days (bill on the 5th, ok if paid 3-7)

-- Source fund
source_fund_id      BIGINT FK → funds.id NULLABLE   -- null = free balance

-- Status
is_active           BOOLEAN DEFAULT TRUE
auto_remind         BOOLEAN DEFAULT TRUE        -- send reminder N days before due_day
remind_days_before  TINYINT DEFAULT 3

-- Payment tracking (reset monthly)
last_paid_at        TIMESTAMP NULLABLE
last_paid_amount    INTEGER NULLABLE
this_month_paid     BOOLEAN DEFAULT FALSE       -- reset on 1st of each month

created_at          TIMESTAMP
updated_at          TIMESTAMP
deleted_at          TIMESTAMP NULLABLE
```

---

### `debts` (NEW v1.2)

Active installments and outstanding loans.

```sql
id                  BIGINT PK
user_id             BIGINT FK → users.id

name                VARCHAR(128) NOT NULL       -- "KPR BCA", "Cicilan Motor", "Paylater Shopee"
debt_type           ENUM('installment','credit_card','personal_loan',
                         'mortgage','paylater','other') NOT NULL

-- Amounts
total_amount        INTEGER NOT NULL            -- original total debt
monthly_installment INTEGER NOT NULL            -- fixed monthly payment
interest_rate       DECIMAL(5,2) NULLABLE       -- annual %, optional
remaining_balance   INTEGER NOT NULL            -- decrements with each payment

-- Schedule
start_date          DATE NULLABLE
due_day             TINYINT NOT NULL            -- day of month payment is due
end_date            DATE NULLABLE               -- estimated payoff date

-- Status
is_active           BOOLEAN DEFAULT TRUE
auto_remind         BOOLEAN DEFAULT TRUE
remind_days_before  TINYINT DEFAULT 3
last_paid_at        TIMESTAMP NULLABLE
this_month_paid     BOOLEAN DEFAULT FALSE

created_at          TIMESTAMP
updated_at          TIMESTAMP
deleted_at          TIMESTAMP NULLABLE
```

---

### `reminders` (updated v1.2)

```sql
id                  BIGINT PK
user_id             BIGINT FK → users.id

type                ENUM('time_based','behavior_based','setup_incomplete',
                         'bill_due','debt_due') NOT NULL   -- NEW: setup_incomplete, bill_due, debt_due

trigger_time        TIME NULLABLE
trigger_days        VARCHAR(32) DEFAULT 'mon,tue,wed,thu,fri,sat,sun'

trigger_condition   JSONB NULLABLE
-- NEW conditions v1.2:
-- {"type": "setup_incomplete", "field": "emergency_fund"}
-- {"type": "setup_incomplete", "field": "bills"}
-- {"type": "setup_incomplete", "field": "income"}
-- {"type": "bill_due", "bill_id": 12, "days_before": 3}
-- {"type": "debt_due", "debt_id": 5, "days_before": 3}
-- Existing:
-- {"type": "no_expense_log", "by_time": "20:00"}
-- {"type": "inactive_days", "threshold": 2}

message_template    TEXT NOT NULL

-- References
linked_bill_id      BIGINT FK → bills.id NULLABLE
linked_debt_id      BIGINT FK → debts.id NULLABLE

is_active           BOOLEAN DEFAULT TRUE
is_system           BOOLEAN DEFAULT FALSE       -- true = created by Butler, not user
last_triggered_at   TIMESTAMP NULLABLE
trigger_count       INTEGER DEFAULT 0

created_at          TIMESTAMP
updated_at          TIMESTAMP
deleted_at          TIMESTAMP NULLABLE
```

---

### `daily_summaries` (updated v1.2)

```sql
id                  BIGINT PK
user_id             BIGINT FK → users.id

summary_date        DATE NOT NULL
summary_type        ENUM('daily','weekly') DEFAULT 'daily'

-- Finance snapshot
total_spent_idr     INTEGER DEFAULT 0
total_income_idr    INTEGER DEFAULT 0
total_saved_idr     INTEGER DEFAULT 0
total_bills_paid    INTEGER DEFAULT 0
budget_remaining    INTEGER NULLABLE
free_balance_eod    INTEGER NULLABLE            -- end-of-day estimated free balance (NEW)

-- Calorie snapshot
total_calories      INTEGER DEFAULT 0
calorie_goal        INTEGER NULLABLE
calorie_status      ENUM('under','on_track','over') NULLABLE

-- Funds snapshot (NEW v1.2)
funds_snapshot      JSONB DEFAULT '{}'          -- {fund_id: balance} at time of summary

-- Engagement
entry_count         INTEGER DEFAULT 0
streak_at_time      INTEGER DEFAULT 0

-- AI
ai_generated_text   TEXT NOT NULL
ai_prompt_version   VARCHAR(16) DEFAULT 'v1'

-- Bills & debts context (NEW)
bills_due_this_week JSONB DEFAULT '[]'          -- upcoming bill names + amounts
debts_due_this_week JSONB DEFAULT '[]'

-- Delivery
was_delivered       BOOLEAN DEFAULT FALSE
delivered_at        TIMESTAMP NULLABLE
delivery_error      TEXT NULLABLE

created_at          TIMESTAMP

UNIQUE (user_id, summary_date, summary_type)
```

---

### `streaks`, `ai_logs` — unchanged from v1.1

---

## Setup-Incomplete Reminders (NEW v1.2)

Butler actively nudges users to complete their financial picture.  
These fire once, at a sensible time, not repeatedly.

| Condition | Trigger timing | Message |
|---|---|---|
| `monthly_income_idr` is null | Day 3 after onboarding, 10am | "Hei {name}, kalau kamu masukin income bulanan, Butler bisa kasih insight yang lebih akurat. Berapa income kamu per bulan?" |
| `has_bills_setup` is false | Day 2 after onboarding, 8pm | "Ada tagihan tetap bulanan nggak? Kos, internet, langganan? Butler bisa ingatkan sebelum jatuh tempo." |
| `has_emergency_fund` is false | Day 5 after onboarding, 7pm | "Dana darurat itu penting banget. Kamu punya nggak? Kalau ada, berapa saldonya sekarang? Biar Butler bisa bantu pantau." |
| `has_debt_declared` is false | Day 4 after onboarding, 7pm | "Ada cicilan aktif nggak, {name}? Motor, KPR, paylater? Butler bisa ingatkan sebelum jatuh tempo biar nggak kena denda." |
| No income logged after 14 days | Day 14, 7pm | "Udah 2 minggu, tapi Butler belum pernah lihat income masuk. Ketik income kamu kalau mau Butler hitung cashflow bulanan." |

**Rules for setup reminders:**
- Fire maximum ONCE per condition
- Mark as sent in `reminders.last_triggered_at`
- If user responds and fills the data → `has_{field} = true` → reminder never fires again
- If user explicitly says "skip" or "nggak ada" → mark the flag as acknowledged (don't re-ask)
- Never send more than one setup reminder per day

---

## Fund Deduction Flow (How Expense Touches Funds)

```
User: "bayar kos 1.5jt"
         │
         ▼
   AI parses: type=bill_payment, amount=1500000, merchant="kos"
         │
         ▼
   Check: does user have a bill named "kos"?
         │
    YES ──┤── Auto-match to bills record
         │    source_fund = bill.source_fund_id (or free_balance if null)
         │    Log entry → update fund balance → confirm to user
         │
    NO ───┤── Check category: recurring? large amount?
              │
              ▼ (amount > 30% daily budget OR looks like a bill)
         Butler: "Ini tagihan tetap? Mau Butler simpan sebagai tagihan bulanan?"
                 [Ya, tagihan tetap] [Nggak, pengeluaran biasa]
                        │
               [tagihan tetap] → add to bills table → set due_day → log entry
               [pengeluaran biasa] → log as regular expense from free_balance
```

---

## Updated AI Summary Context Payload v1.2

```json
{
  "user": {
    "name": "Andi",
    "monthly_income_idr": 5000000,
    "daily_budget_idr": 150000,
    "tracking_mode": "both",
    "daily_calorie_goal": 2000
  },
  "date": "Senin, 19 Mei 2026",
  "entries": [
    { "type": "expense", "amount": 45000, "category": "food_drink", "merchant": "GoFood" },
    { "type": "expense", "amount": 23000, "category": "transport", "merchant": "Grab" }
  ],
  "totals": {
    "spent_idr": 68000,
    "income_idr": 0,
    "budget_remaining_idr": 82000,
    "free_balance_estimate": 3100000,
    "calories_consumed": 1200,
    "calorie_remaining": 800
  },
  "funds": [
    { "name": "Dana Darurat", "type": "emergency_fund", "balance": 3000000 },
    { "name": "Nabung Liburan", "type": "sinking_fund", "balance": 850000, "target": 3000000, "target_date": "2026-12-01", "on_track": true }
  ],
  "upcoming": {
    "bills_due_3_days": [
      { "name": "Internet", "amount": 250000, "due_day": 22 }
    ],
    "debts_due_3_days": []
  },
  "streak": {
    "log_current": 4,
    "log_longest": 7
  },
  "setup_flags": {
    "has_income_set": true,
    "has_bills_setup": true,
    "has_emergency_fund": true,
    "has_debt_declared": false
  }
}
```

---

## AI Intent List (Updated v1.2)

| Intent | Example input | What Butler does |
|---|---|---|
| `log_expense` | "makan 35k", "grab 23rb" | Parse → save entry → deduct from fund |
| `log_meal` | "makan nasi goreng 300gr" | Parse → estimate cal → save |
| `log_income` | "gajian 5jt", "dapet freelance 500rb" | Parse → save → update free balance |
| `log_saving` | "nabung 200k ke dana darurat" | Parse → save → credit fund balance |
| `log_bill_payment` | "bayar kos 1.5jt", "bayar internet" | Match to bills → save → mark paid |
| `log_debt_payment` | "bayar cicilan motor 800rb" | Match to debts → save → reduce remaining |
| `log_sinking_deposit` | "masukin 300k ke nabung liburan" | Credit sinking fund → save |
| `add_bill` | "tambahin tagihan Netflix 65rb tiap tanggal 15" | Create bills record |
| `add_sinking_fund` | "buat sinking fund beli laptop target 8jt" | Create fund record → ask target date |
| `query_balance` | "saldo dana darurat berapa?" | Fetch fund balance → reply |
| `query_summary` | "rangkuman hari ini" | Trigger on-demand summary |
| `query_spending` | "udah keluar berapa hari ini?" | Sum today's expenses → reply |
| `set_reminder` | "ingetin gym jam 7 malem" | Create time-based reminder |
| `unknown` | ambiguous input | Ask for clarification |

---

## Confidence Score Behavior (unchanged from v1.1)

| Score | Butler does |
|---|---|
| ≥ 0.90 | Auto-confirm with inline keyboard |
| 0.75–0.89 | Show parsed data, require explicit confirm |
| 0.50–0.74 | Highlight uncertain fields, ask to verify |
| < 0.50 | Don't guess — ask clarifying question |

---

## Error Response Templates (updated v1.2)

| Situation | Butler says |
|---|---|
| Fund not found | "Dana '{name}' belum ada. Mau Butler buatin? [Ya] [Nggak]" |
| Bill not recognized | "Ini tagihan tetap baru? Mau disimpan biar Butler ingatkan tiap bulan? [Ya] [Nggak]" |
| Amount ambiguous | "Bayar berapa, {name}? Butuh nominalnya." |
| Fund balance insufficient | "Saldo {fund_name} cuma Rp {balance}. Tetap lanjut catat? [Ya] [Nggak]" |
| Parse fails | "Butler nggak nangkep yang ini. Contoh: '50k makan siang' atau 'bayar kos 1.5jt'" |
| No entries today | "Belum ada catatan hari ini, {name}. Coba ketik pengeluaran terakhir kamu." |
| Bill already paid this month | "Kayaknya {bill_name} udah kamu bayar bulan ini (Rp {amount}). Ini pembayaran baru atau koreksi?" |

---

## What's Still Out of Scope (v2.md)

- Dashboard web app
- Voice message logging
- Receipt / photo scanning
- Weekly / monthly summary
- Multi-currency
- Nutrition macros (protein, carbs, fat)
- Asset tracking (emas, tanah)
- Export to CSV / PDF
- iOS native client
- Pattern-based reminders (needs 2+ weeks data)
- Shared finances (pasangan, keluarga)
- Investment return tracking

---

*Last updated: May 2026 — v1.2 Financial Structure Expansion*
