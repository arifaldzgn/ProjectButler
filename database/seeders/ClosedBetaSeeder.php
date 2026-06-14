<?php

namespace Database\Seeders;

use App\Models\User;
use App\Models\Fund;
use App\Models\Entry;
use App\Models\Bill;
use App\Models\Debt;
use App\Models\Streak;
use App\Models\FundTransaction;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Closed Beta Seeder — Week 1 (Jun 1–7, 2026)
 * 5 testers: yusrilanwr, ravbas, iqbalj, fitrianifitriani, jonid
 */
class ClosedBetaSeeder extends Seeder
{
    // Beta period: 1 week ending today
    private Carbon $betaStart;
    private Carbon $betaEnd;

    public function run(): void
    {
        $this->betaStart = Carbon::parse('2026-06-01 08:00:00', 'Asia/Jakarta');
        $this->betaEnd   = Carbon::parse('2026-06-07 23:00:00', 'Asia/Jakarta');

        $testers = [
            [
                'username'      => 'yusrilanwr',
                'name'          => 'Yusril Anwar',
                'telegram_id'   => 9900001,
                'mode'          => 'both',
                'income'        => 6500000,
                'budget'        => 3000000,
                'calorie_goal'  => 2000,
                'activity'      => 'high',   // logs almost every day
                'persona'       => 'saver',  // saves a lot, low spending
            ],
            [
                'username'      => 'ravbas',
                'name'          => 'Rav Bas',
                'telegram_id'   => 9900002,
                'mode'          => 'finance',
                'income'        => 8000000,
                'budget'        => 5000000,
                'calorie_goal'  => null,
                'activity'      => 'medium', // skips a day or two
                'persona'       => 'spender', // spends more, impulse buys
            ],
            [
                'username'      => 'iqbalj',
                'name'          => 'Iqbal Jauhari',
                'telegram_id'   => 9900003,
                'mode'          => 'both',
                'income'        => 5000000,
                'budget'        => 2500000,
                'calorie_goal'  => 2200,
                'activity'      => 'high',
                'persona'       => 'balanced',
            ],
            [
                'username'      => 'fitrianifitriani',
                'name'          => 'Fitriani',
                'telegram_id'   => 9900004,
                'mode'          => 'calorie',
                'income'        => 4000000,
                'budget'        => null,
                'calorie_goal'  => 1800,
                'activity'      => 'medium',
                'persona'       => 'health_focused',
            ],
            [
                'username'      => 'jonid',
                'name'          => 'Joni D',
                'telegram_id'   => 9900005,
                'mode'          => 'finance',
                'income'        => 12000000,
                'budget'        => 7000000,
                'calorie_goal'  => null,
                'activity'      => 'low',    // only logged a few days
                'persona'       => 'high_earner',
            ],
        ];

        foreach ($testers as $t) {
            $this->seedUser($t);
        }

        $this->command->info('✅ Closed beta seed complete — 5 testers, 7 days of data.');
    }

    private function seedUser(array $cfg): void
    {
        // Avoid duplicates
        User::where('telegram_chat_id', $cfg['telegram_id'])->forceDelete();

        $user = User::create([
            'name'                  => $cfg['name'],
            'telegram_chat_id'      => $cfg['telegram_id'],
            'telegram_username'     => $cfg['username'],
            'timezone'              => 'Asia/Jakarta',
            'tracking_mode'         => $cfg['mode'],
            'monthly_income_idr'    => $cfg['income'],
            'monthly_budget_idr'    => $cfg['budget'],
            'daily_budget_idr'      => $cfg['budget'] ? (int)($cfg['budget'] / 30) : null,
            'daily_calorie_goal'    => $cfg['calorie_goal'],
            'calorie_goal_type'     => $cfg['calorie_goal'] ? 'maintenance' : null,
            'onboarding_step'       => 'complete',
            'onboarding_complete_at'=> $this->betaStart->copy()->subHours(2),
            'onboarding_started_at' => $this->betaStart->copy()->subHours(3),
            'last_active_at'        => $this->betaEnd,
            'has_emergency_fund'    => in_array($cfg['persona'], ['saver', 'balanced']),
            'has_bills_setup'       => true,
            'has_income_set'        => $cfg['income'] > 0,
            'has_debt_declared'     => in_array($cfg['persona'], ['spender', 'high_earner']),
            'daily_summary_enabled' => true,
            'summary_time'          => '21:00',
        ]);

        // Create accounts / funds
        $accounts = $this->createFunds($user, $cfg);

        // Create bills
        $this->createBills($user, $accounts);

        // Create debts
        if (in_array($cfg['persona'], ['spender', 'high_earner'])) {
            $this->createDebts($user);
        }

        // Create entries for each day in the beta period
        $this->createEntries($user, $cfg, $accounts);

        // Streak
        $activeDays = $cfg['activity'] === 'high' ? 7 : ($cfg['activity'] === 'medium' ? 5 : 3);
        Streak::create([
            'user_id'           => $user->id,
            'log_current'       => $activeDays,
            'log_longest'       => $activeDays,
            'log_last_date'     => $this->betaEnd->toDateString(),
            'expense_current'   => $cfg['mode'] !== 'calorie' ? $activeDays : 0,
            'expense_longest'   => $cfg['mode'] !== 'calorie' ? $activeDays : 0,
            'expense_last_date' => $cfg['mode'] !== 'calorie' ? $this->betaEnd->toDateString() : null,
            'meal_current'      => $cfg['calorie_goal'] ? $activeDays : 0,
            'meal_longest'      => $cfg['calorie_goal'] ? $activeDays : 0,
            'meal_last_date'    => $cfg['calorie_goal'] ? $this->betaEnd->toDateString() : null,
        ]);
    }

    private function createFunds(User $user, array $cfg): array
    {
        $accounts = [];

        // Main bank account
        $bca = Fund::create([
            'user_id'            => $user->id,
            'fund_type'          => 'spending_budget',
            'name'               => 'BCA',
            'current_balance'    => $this->randomBalance($cfg['persona'], 'bank'),
            'initial_balance'    => 1000000,
            'is_default_spending'=> true,
            'is_active'          => true,
        ]);
        $accounts['bca'] = $bca;
        $user->update(['default_account_id' => $bca->id]);

        // GoPay
        $gopay = Fund::create([
            'user_id'         => $user->id,
            'fund_type'       => 'spending_budget',
            'name'            => 'GoPay',
            'current_balance' => $this->randomBalance($cfg['persona'], 'ewallet'),
            'initial_balance' => 0,
            'is_active'       => true,
        ]);
        $accounts['gopay'] = $gopay;

        // Emergency fund (for savers/balanced)
        if ($cfg['persona'] === 'saver' || $cfg['persona'] === 'balanced') {
            $ef = Fund::create([
                'user_id'              => $user->id,
                'fund_type'            => 'emergency_fund',
                'name'                 => 'Dana Darurat',
                'current_balance'      => rand(8000000, 20000000),
                'initial_balance'      => 5000000,
                'target_amount'        => 30000000,
                'target_date'          => now()->addMonths(18)->toDateString(),
                'monthly_contribution' => 500000,
                'progress_pct'         => rand(25, 65),
                'is_active'            => true,
            ]);
            $accounts['ef'] = $ef;
        }

        // Sinking fund — vacation
        $sf = Fund::create([
            'user_id'              => $user->id,
            'fund_type'            => 'sinking_fund',
            'name'                 => 'Liburan Akhir Tahun',
            'current_balance'      => rand(500000, 3000000),
            'initial_balance'      => 0,
            'target_amount'        => 5000000,
            'target_date'          => '2026-12-20',
            'monthly_contribution' => 700000,
            'progress_pct'         => rand(10, 60),
            'is_active'            => true,
        ]);
        $accounts['sf'] = $sf;

        // High earner extra: investment goal
        if ($cfg['persona'] === 'high_earner') {
            $inv = Fund::create([
                'user_id'              => $user->id,
                'fund_type'            => 'financial_goal',
                'name'                 => 'DP Rumah',
                'current_balance'      => rand(20000000, 50000000),
                'initial_balance'      => 10000000,
                'target_amount'        => 150000000,
                'target_date'          => '2028-01-01',
                'monthly_contribution' => 3000000,
                'progress_pct'         => rand(15, 35),
                'is_active'            => true,
            ]);
            $accounts['inv'] = $inv;
        }

        return $accounts;
    }

    private function randomBalance(string $persona, string $type): int
    {
        return match ([$persona, $type]) {
            ['saver',        'bank']    => rand(8000000, 15000000),
            ['saver',        'ewallet'] => rand(200000, 800000),
            ['spender',      'bank']    => rand(1500000, 4000000),
            ['spender',      'ewallet'] => rand(50000, 500000),
            ['balanced',     'bank']    => rand(4000000, 9000000),
            ['balanced',     'ewallet'] => rand(100000, 600000),
            ['health_focused','bank']   => rand(3000000, 7000000),
            ['health_focused','ewallet']=> rand(100000, 400000),
            ['high_earner',  'bank']    => rand(15000000, 40000000),
            ['high_earner',  'ewallet'] => rand(500000, 2000000),
            default                     => rand(1000000, 5000000),
        };
    }

    private function createBills(User $user, array $accounts): void
    {
        $bills = [
            ['name' => 'Netflix',         'amount' => 54000,   'due_day' => 5,  'category' => 'entertainment'],
            ['name' => 'Spotify',         'amount' => 27000,   'due_day' => 10, 'category' => 'entertainment'],
            ['name' => 'Listrik PLN',     'amount' => 350000,  'due_day' => 20, 'category' => 'utilities'],
            ['name' => 'Internet Indihome','amount' => 385000, 'due_day' => 15, 'category' => 'utilities'],
        ];

        foreach ($bills as $b) {
            Bill::create([
                'user_id'         => $user->id,
                'name'            => $b['name'],
                'amount'          => $b['amount'],
                'category'        => $b['category'],
                'due_day'         => $b['due_day'],
                'is_active'       => true,
                'auto_remind'     => true,
                'remind_days_before' => 3,
                'this_month_paid' => $b['due_day'] < now()->day,
                'last_paid_at'    => $b['due_day'] < now()->day ? now()->day($b['due_day']) : null,
                'source_fund_id'  => $accounts['bca']->id,
            ]);
        }
    }

    private function createDebts(User $user): void
    {
        Debt::create([
            'user_id'             => $user->id,
            'name'                => 'Cicilan HP Samsung',
            'debt_type'           => 'installment',
            'total_amount'        => 12000000,
            'remaining_balance'   => rand(4000000, 10000000),
            'monthly_installment' => 1000000,
            'interest_rate'       => 0.00,
            'due_day'             => 25,
            'start_date'          => now()->subMonths(4)->toDateString(),
            'end_date'            => now()->addMonths(8)->toDateString(),
            'is_active'           => true,
            'auto_remind'         => true,
            'this_month_paid'     => false,
        ]);

        Debt::create([
            'user_id'             => $user->id,
            'name'                => 'Pinjaman Pribadi Teman',
            'debt_type'           => 'personal_loan',
            'total_amount'        => 5000000,
            'remaining_balance'   => rand(1000000, 4000000),
            'monthly_installment' => 500000,
            'interest_rate'       => 0.00,
            'due_day'             => 1,
            'is_active'           => true,
            'auto_remind'         => true,
            'this_month_paid'     => true,
        ]);
    }

    private function createEntries(User $user, array $cfg, array $accounts): void
    {
        $activeDays = $cfg['activity'] === 'high' ? 7 : ($cfg['activity'] === 'medium' ? 5 : 3);

        // Which days in the 7-day window are active?
        $allDays = collect(range(0, 6))->map(fn($i) => $this->betaStart->copy()->addDays($i)->toDateString());
        $skipCount = 7 - $activeDays;

        // Low activity skips first/last days; medium skips 2 random days
        if ($cfg['activity'] === 'low') {
            $activeDates = $allDays->slice(2, 3)->values(); // only middle 3 days
        } elseif ($cfg['activity'] === 'medium') {
            $activeDates = $allDays->reject(fn($d) => in_array($d, [
                $allDays[1], $allDays[4]
            ]))->values();
        } else {
            $activeDates = $allDays;
        }

        foreach ($activeDates as $date) {
            $this->seedDayEntries($user, $cfg, $accounts, Carbon::parse($date, 'Asia/Jakarta'));
        }
    }

    private function seedDayEntries(User $user, array $cfg, array $accounts, Carbon $day): void
    {
        $persona = $cfg['persona'];
        $mode    = $cfg['mode'];

        // ── Income: log salary on Jun 1 ─────────────────────────────
        if ($day->day === 1 && $cfg['income'] && $mode !== 'calorie') {
            $this->makeEntry($user, $accounts['bca'], 'income', $cfg['income'], [
                'category' => 'salary',
                'merchant' => 'Gaji Bulanan',
                'note'     => 'Transfer gaji',
                'entry_time' => $day->copy()->setTime(9, 0),
            ]);
        }

        // ── Expenses ─────────────────────────────────────────────────
        if ($mode !== 'calorie') {
            $this->seedExpenses($user, $cfg, $accounts, $day);
        }

        // ── Meals ───────────────────────────────────────────────────
        if ($mode !== 'finance') {
            $this->seedMeals($user, $cfg, $accounts, $day);
        }

        // ── Savings deposit (every 2 days for savers) ───────────────
        if (isset($accounts['sf']) && $day->dayOfWeek === Carbon::MONDAY && $mode !== 'calorie') {
            $amount = rand(200000, 700000);
            $entry  = $this->makeEntry($user, $accounts['bca'], 'sinking_fund_deposit', $amount, [
                'category'   => 'saving',
                'note'       => 'Top-up sinking fund liburan',
                'entry_time' => $day->copy()->setTime(20, 30),
            ]);
            $accounts['sf']->credit($amount, $entry->id, 'Top-up dari BCA');
        }
    }

    private function seedExpenses(User $user, array $cfg, array $accounts, Carbon $day): void
    {
        $persona = $cfg['persona'];
        $account = rand(0, 10) > 3 ? $accounts['bca'] : $accounts['gopay'];

        // Morning coffee/breakfast
        if (rand(0, 10) > 2) {
            $this->makeEntry($user, $account, 'expense', rand(18000, 45000), [
                'category' => 'food',
                'merchant' => collect(['Kopi Kenangan', 'Janji Jiwa', 'Indomaret', 'Warung Nasi Padang'])->random(),
                'entry_time' => $day->copy()->setTime(rand(7, 9), rand(0, 59)),
            ]);
        }

        // Lunch
        $this->makeEntry($user, $account, 'expense', rand(20000, 65000), [
            'category'  => 'food',
            'merchant'  => collect(['Warteg', 'McDonald\'s', 'KFC', 'GoFood order', 'Mie Gacoan', 'Geprek Bensu'])->random(),
            'entry_time'=> $day->copy()->setTime(rand(11, 13), rand(0, 59)),
        ]);

        // Persona-specific spending
        match ($persona) {
            'spender' => $this->spenderExtras($user, $account, $day),
            'high_earner' => $this->highEarnerExtras($user, $account, $day),
            'saver' => rand(0, 10) > 7 ? $this->saverExtras($user, $account, $day) : null,
            default => $this->balancedExtras($user, $account, $day),
        };

        // Transport
        if (rand(0, 10) > 4) {
            $this->makeEntry($user, $account, 'expense', rand(5000, 35000), [
                'category' => 'transport',
                'merchant' => collect(['Grab', 'Gojek', 'Busway', 'KRL'])->random(),
                'entry_time' => $day->copy()->setTime(rand(6, 8), rand(0, 59)),
            ]);
        }

        // Dinner
        $this->makeEntry($user, $account, 'expense', rand(20000, 80000), [
            'category'  => 'food',
            'merchant'  => collect(['Warteg', 'GoFood', 'Pecel Lele', 'Sate Madura', 'Pizza Hut'])->random(),
            'entry_time'=> $day->copy()->setTime(rand(18, 20), rand(0, 59)),
        ]);
    }

    private function spenderExtras(User $user, Fund $account, Carbon $day): void
    {
        // Impulse shopping
        if (rand(0, 10) > 4) {
            $this->makeEntry($user, $account, 'expense', rand(100000, 500000), [
                'category' => 'shopping',
                'merchant' => collect(['Tokopedia', 'Shopee', 'H&M', 'Zara', 'Uniqlo'])->random(),
                'entry_time' => $day->copy()->setTime(rand(14, 22), rand(0, 59)),
            ]);
        }
        // Entertainment
        if (rand(0, 10) > 5) {
            $this->makeEntry($user, $account, 'expense', rand(50000, 200000), [
                'category' => 'entertainment',
                'merchant' => collect(['CGV', 'XXI', 'Timezone', 'Karaoke'])->random(),
                'entry_time' => $day->copy()->setTime(rand(15, 22), rand(0, 59)),
            ]);
        }
    }

    private function highEarnerExtras(User $user, Fund $account, Carbon $day): void
    {
        if (rand(0, 10) > 5) {
            $this->makeEntry($user, $account, 'expense', rand(200000, 1500000), [
                'category' => collect(['dining', 'shopping', 'health', 'hobbies'])->random(),
                'merchant' => collect(['IKEA', 'Fitness First', 'Ranch Market', 'Starbucks Reserve', 'Apple Store'])->random(),
                'entry_time' => $day->copy()->setTime(rand(12, 21), rand(0, 59)),
            ]);
        }
    }

    private function saverExtras(User $user, Fund $account, Carbon $day): void
    {
        $this->makeEntry($user, $account, 'expense', rand(10000, 50000), [
            'category' => collect(['household', 'personal_care'])->random(),
            'merchant' => collect(['Alfamart', 'Indomaret', 'Superindo'])->random(),
            'entry_time' => $day->copy()->setTime(rand(16, 19), rand(0, 59)),
        ]);
    }

    private function balancedExtras(User $user, Fund $account, Carbon $day): void
    {
        if (rand(0, 10) > 5) {
            $this->makeEntry($user, $account, 'expense', rand(30000, 200000), [
                'category' => collect(['shopping', 'health', 'household', 'entertainment'])->random(),
                'merchant' => collect(['Guardian', 'Watson', 'Gramedia', 'Sports Station'])->random(),
                'entry_time' => $day->copy()->setTime(rand(15, 21), rand(0, 59)),
            ]);
        }
    }

    private function seedMeals(User $user, array $cfg, array $accounts, Carbon $day): void
    {
        $goal = $cfg['calorie_goal'] ?? 2000;

        $meals = [
            ['food_item' => 'Nasi Uduk + Ayam Goreng', 'calories' => rand(450, 600), 'protein' => 28.0, 'carbs' => 55.0, 'fat' => 18.0, 'time' => [7, 8]],
            ['food_item' => 'Soto Ayam + Nasi',        'calories' => rand(350, 480), 'protein' => 22.0, 'carbs' => 48.0, 'fat' => 10.0, 'time' => [12, 13]],
            ['food_item' => 'Mie Goreng Telur',         'calories' => rand(380, 520), 'protein' => 15.0, 'carbs' => 62.0, 'fat' => 14.0, 'time' => [19, 20]],
        ];

        // Persona adjustments
        if ($cfg['persona'] === 'health_focused') {
            $meals = [
                ['food_item' => 'Oatmeal + Pisang',         'calories' => rand(280, 360), 'protein' => 10.0, 'carbs' => 52.0, 'fat' => 5.0,  'time' => [6, 7]],
                ['food_item' => 'Salad Ayam Panggang',       'calories' => rand(300, 400), 'protein' => 35.0, 'carbs' => 20.0, 'fat' => 8.0,  'time' => [12, 13]],
                ['food_item' => 'Yogurt + Granola',          'calories' => rand(200, 280), 'protein' => 8.0,  'carbs' => 35.0, 'fat' => 6.0,  'time' => [15, 16]],
                ['food_item' => 'Nasi Merah + Ikan Bakar',  'calories' => rand(380, 460), 'protein' => 32.0, 'carbs' => 45.0, 'fat' => 9.0,  'time' => [18, 19]],
            ];
        }

        foreach ($meals as $meal) {
            if (rand(0, 10) > 1) {
                $this->makeEntry($user, $accounts['bca'], 'meal', null, [
                    'food_item'           => $meal['food_item'],
                    'calories'            => $meal['calories'],
                    'protein_g'           => $meal['protein'],
                    'carbs_g'             => $meal['carbs'],
                    'fat_g'               => $meal['fat'],
                    'is_calorie_estimated'=> rand(0, 10) > 3,
                    'entry_time'          => $day->copy()->setTime(rand(...$meal['time']), rand(0, 59)),
                ]);
            }
        }
    }

    private function makeEntry(User $user, Fund $fund, string $type, ?int $amount, array $extra): Entry
    {
        $entryTime = $extra['entry_time'] ?? now();

        $entry = Entry::create(array_merge([
            'user_id'        => $user->id,
            'type'           => $type,
            'amount'         => $amount,
            'currency'       => 'IDR',
            'source_fund_id' => in_array($type, ['meal']) ? null : $fund->id,
            'source_fund_confirmed' => true,
            'entry_time'     => $entryTime,
            'ai_raw_input'   => 'beta_seed',
            'ai_intent'      => $type,
            'ai_confidence'  => round(rand(85, 100) / 100, 3),
            'ai_prompt_version' => 'v1.2',
            'confirmed_at'   => $entryTime,
            'is_undone'      => false,
        ], $extra));

        // Deduct from fund balance for expenses
        if (in_array($type, ['expense', 'bill_payment']) && $amount) {
            $before = $fund->current_balance;
            $fund->update(['current_balance' => max(0, $before - $amount)]);
            FundTransaction::create([
                'fund_id'          => $fund->id,
                'user_id'          => $user->id,
                'entry_id'         => $entry->id,
                'transaction_type' => 'withdrawal',
                'amount'           => $amount,
                'balance_before'   => $before,
                'balance_after'    => max(0, $before - $amount),
            ]);
        }

        if ($type === 'income' && $amount) {
            $before = $fund->current_balance;
            $fund->update(['current_balance' => $before + $amount]);
            FundTransaction::create([
                'fund_id'          => $fund->id,
                'user_id'          => $user->id,
                'entry_id'         => $entry->id,
                'transaction_type' => 'deposit',
                'amount'           => $amount,
                'balance_before'   => $before,
                'balance_after'    => $before + $amount,
            ]);
        }

        return $entry;
    }
}
