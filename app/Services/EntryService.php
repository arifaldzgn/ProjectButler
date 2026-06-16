<?php

namespace App\Services;

use App\Models\Category;
use App\Models\Entry;
use App\Models\User;
use Carbon\Carbon;

class EntryService
{
    /**
     * Create a pending entry (not yet confirmed by user).
     * Handles all v1.2 entry types.
     */
    public function createPendingEntry(User $user, array $parsed, string $rawMessage): Entry
    {
        $now = Carbon::now($user->timezone);
        $intent = $parsed['intent'];

        $entryTime = $now;
        if (!empty($parsed['entry_time'])) {
            try {
                $entryTime = $now->copy()->setTimeFromTimeString($parsed['entry_time']);
            } catch (\Exception $e) {
                $entryTime = $now;
            }
        }

        $metadata = [];
        if (!empty($parsed['fund_name'])) $metadata['fund_name'] = $parsed['fund_name'];
        if (!empty($parsed['source_fund'])) $metadata['source_fund'] = $parsed['source_fund'];
        if (!empty($parsed['target_fund'])) $metadata['target_fund'] = $parsed['target_fund'];
        if (!empty($parsed['direction'])) $metadata['direction'] = $parsed['direction'];
        if (!empty($parsed['source_fund_id'])) $metadata['source_fund_id'] = (int) $parsed['source_fund_id'];
        if (!empty($parsed['target_fund_id'])) $metadata['target_fund_id'] = (int) $parsed['target_fund_id'];

        $data = [
            'user_id' => $user->id,
            'type' => $this->mapIntentToType($intent),
            'ai_raw_input' => $rawMessage,
            'ai_intent' => $intent,
            'ai_confidence' => $parsed['confidence'] ?? 0.5,
            'ai_prompt_version' => 'parse_v1.3',
            'entry_time' => $entryTime,
            'metadata' => $metadata,
            'confirmed_at' => null,
        ];

        switch ($intent) {
            case 'log_expense':
                $data['amount']   = $parsed['amount'] ?? 0;
                $data['category'] = $parsed['category'] ?? 'other';
                $data['merchant'] = $parsed['merchant'] ?? null;
                $data['note']     = $parsed['note'] ?? null;
                // Resolve custom category if user has one matching
                $data['category_id'] = $this->resolveCustomCategoryId($user, $parsed['category'] ?? null);
                break;

            case 'log_meal':
                $data['food_item'] = $parsed['food_item'] ?? 'Unknown food';
                $data['calories'] = $parsed['calories'] ?? null;
                $data['protein_g'] = $parsed['protein_g'] ?? null;
                $data['carbs_g'] = $parsed['carbs_g'] ?? null;
                $data['fat_g'] = $parsed['fat_g'] ?? null;
                $data['is_calorie_estimated'] = $parsed['is_calorie_estimated'] ?? true;
                $data['note'] = $parsed['note'] ?? null;
                break;

            case 'log_saving':
                $data['amount'] = $parsed['amount'] ?? 0;
                $data['note'] = $parsed['note'] ?? null;
                break;

            case 'log_income':
                $data['amount'] = $parsed['amount'] ?? 0;
                $data['note'] = $parsed['note'] ?? ($parsed['source'] ?? null);
                break;

            case 'log_bill_payment':
                $data['amount'] = $parsed['amount'] ?? 0;
                $data['merchant'] = $parsed['bill_name'] ?? null;
                $data['note'] = $parsed['bill_name'] ?? null;
                break;

            case 'log_debt_payment':
                $data['amount'] = $parsed['amount'] ?? 0;
                $data['note'] = $parsed['debt_name'] ?? null;
                break;

            case 'log_sinking_deposit':
                $data['amount'] = $parsed['amount'] ?? 0;
                $data['note'] = $parsed['fund_name'] ?? null;
                break;

            case 'transfer_fund':
                $data['amount'] = $parsed['amount'] ?? 0;
                $direction = $parsed['direction'] ?? 'internal';
                $data['note'] = match ($direction) {
                    'in'  => "Terima ke " . ($parsed['target_fund'] ?? 'akun'),
                    'out' => "Transfer dari " . ($parsed['source_fund'] ?? 'akun'),
                    default => "Transfer ke " . ($parsed['target_fund'] ?? 'dana lain'),
                };
                // The resolved source account is stored on the entry directly.
                if (!empty($parsed['source_fund_id'])) {
                    $data['source_fund_id'] = (int) $parsed['source_fund_id'];
                    $data['source_fund_confirmed'] = true;
                }
                break;
        }

        return Entry::create($data);
    }

    /**
     * Confirm a pending entry (user tapped ✅).
     */
    public function confirmEntry(Entry $entry): Entry
    {
        $entry->update(['confirmed_at' => now()]);
        return $entry->fresh();
    }

    /**
     * Cancel a pending entry (user tapped ❌).
     */
    public function cancelEntry(Entry $entry): void
    {
        $entry->delete();
    }

    // ── Query Methods ───────────────────────────────────────────────────

    public function getTodaySpending(User $user): int
    {
        $today = Carbon::now($user->timezone)->toDateString();
        return (int) Entry::forUser($user->id)
            ->whereIn('type', ['expense', 'bill_payment'])
            ->confirmed()->forDate($today)->sum('amount');
    }

    public function getTodayCalories(User $user): int
    {
        $today = Carbon::now($user->timezone)->toDateString();
        return (int) Entry::forUser($user->id)
            ->meals()->confirmed()->forDate($today)->sum('calories');
    }

    /**
     * Get today's macro totals: protein, carbs, fat in grams.
     */
    public function getTodayMacros(User $user): array
    {
        $today = Carbon::now($user->timezone)->toDateString();
        $row = Entry::forUser($user->id)->meals()->confirmed()->forDate($today)
            ->selectRaw('SUM(protein_g) as protein, SUM(carbs_g) as carbs, SUM(fat_g) as fat')
            ->first();

        return [
            'protein' => round((float) ($row->protein ?? 0), 1),
            'carbs'   => round((float) ($row->carbs ?? 0), 1),
            'fat'     => round((float) ($row->fat ?? 0), 1),
        ];
    }

    public function getTodayIncome(User $user): int
    {
        $today = Carbon::now($user->timezone)->toDateString();
        return (int) Entry::forUser($user->id)
            ->income()->confirmed()->forDate($today)->sum('amount');
    }

    public function getTodayBillsPaid(User $user): int
    {
        $today = Carbon::now($user->timezone)->toDateString();
        return (int) Entry::forUser($user->id)
            ->billPayments()->confirmed()->forDate($today)->sum('amount');
    }

    public function getMonthSpending(User $user): int
    {
        $now = Carbon::now($user->timezone);
        return (int) Entry::forUser($user->id)
            ->expenses()->confirmed()->forMonth($now->year, $now->month)->sum('amount');
    }

    public function getMonthIncome(User $user): int
    {
        $now = Carbon::now($user->timezone);
        return (int) Entry::forUser($user->id)
            ->income()->confirmed()->forMonth($now->year, $now->month)->sum('amount');
    }

    public function getBudgetRemaining(User $user): ?int
    {
        if (!$user->daily_budget_idr) {
            return null;
        }
        return $user->daily_budget_idr - $this->getTodaySpending($user);
    }

    public function getTotalSavings(User $user): int
    {
        return (int) Entry::forUser($user->id)
            ->savings()->confirmed()->sum('amount');
    }

    public function getMonthSavings(User $user): int
    {
        $now = Carbon::now($user->timezone);
        return (int) Entry::forUser($user->id)
            ->savings()->confirmed()->forMonth($now->year, $now->month)->sum('amount');
    }

    public function getTodayEntries(User $user): \Illuminate\Database\Eloquent\Collection
    {
        $today = Carbon::now($user->timezone)->toDateString();
        return Entry::forUser($user->id)->confirmed()->forDate($today)->orderBy('entry_time')->get();
    }

    public function getTodayEntryCount(User $user): int
    {
        $today = Carbon::now($user->timezone)->toDateString();
        return Entry::forUser($user->id)->confirmed()->forDate($today)->count();
    }

    /**
     * Filtered, scoped analytics over the user's own confirmed entries.
     * Read-only aggregation — all inputs are whitelisted and bound, no raw SQL.
     *
     * $filters: [
     *   'metric'     => sum|count|average                 (default sum)
     *   'entry_type' => expense|income|meal|saving|all     (default expense)
     *   'category'   => ?string (loose match on category text column)
     *   'merchant'   => ?string (loose match on merchant + note + food_item)
     *   'period'     => today|yesterday|week|month|last_month|all  (default month)
     * ]
     *
     * Returns a shape the TelegramService formatter renders directly.
     */
    public function analyticsQuery(User $user, array $filters): array
    {
        $metric    = in_array($filters['metric'] ?? 'sum', ['sum', 'count', 'average'], true) ? $filters['metric'] : 'sum';
        $entryType = in_array($filters['entry_type'] ?? 'expense', ['expense', 'income', 'meal', 'saving', 'all'], true) ? $filters['entry_type'] : 'expense';
        $period    = in_array($filters['period'] ?? 'month', ['today', 'yesterday', 'week', 'month', 'last_month', 'all'], true) ? $filters['period'] : 'month';
        $category  = isset($filters['category']) ? trim((string) $filters['category']) : '';
        $merchant  = isset($filters['merchant']) ? trim((string) $filters['merchant']) : '';

        [$start, $end] = $this->periodRange($user, $period);

        $q = Entry::forUser($user->id)->confirmed()
            ->whereBetween('entry_time', [$start, $end]);

        if ($entryType !== 'all') {
            $q->where('type', $entryType);
        }

        if ($category !== '') {
            $q->whereRaw('LOWER(category) LIKE ?', ['%' . mb_strtolower($category) . '%']);
        }

        if ($merchant !== '') {
            $kw = '%' . mb_strtolower($merchant) . '%';
            $q->where(function ($sub) use ($kw) {
                $sub->whereRaw('LOWER(merchant) LIKE ?', [$kw])
                    ->orWhereRaw('LOWER(note) LIKE ?', [$kw])
                    ->orWhereRaw('LOWER(food_item) LIKE ?', [$kw]);
            });
        }

        // Calorie questions sum the calories column; everything else sums amount.
        $isCalorie = ($entryType === 'meal' && $metric !== 'count');
        $sumColumn = $isCalorie ? 'calories' : 'amount';

        $count = (clone $q)->count();
        $sum   = (int) (clone $q)->sum($sumColumn);

        $value = match ($metric) {
            'count'   => $count,
            'average' => $this->perDayAverage($sum, $start, $end),
            default   => $sum,
        };

        return [
            'metric'       => $metric,
            'entry_type'   => $entryType,
            'is_calorie'   => $isCalorie,
            'value'        => $value,
            'count'        => $count,
            'category'     => $category ?: null,
            'merchant'     => $merchant ?: null,
            'period'       => $period,
            'period_label' => $this->periodLabel($period),
        ];
    }

    /**
     * Resolve a named period into a [start, end] Carbon range in the user's tz.
     * "week" = the last 7 days ending today (intuitive, not ISO Mon–Sun).
     */
    private function periodRange(User $user, string $period): array
    {
        $now = Carbon::now($user->timezone);

        return match ($period) {
            'today'      => [$now->copy()->startOfDay(), $now->copy()->endOfDay()],
            'yesterday'  => [$now->copy()->subDay()->startOfDay(), $now->copy()->subDay()->endOfDay()],
            'week'       => [$now->copy()->subDays(6)->startOfDay(), $now->copy()->endOfDay()],
            'last_month' => [$now->copy()->subMonthNoOverflow()->startOfMonth(), $now->copy()->subMonthNoOverflow()->endOfMonth()],
            'all'        => [Carbon::create(2000, 1, 1, 0, 0, 0, $user->timezone), $now->copy()->endOfDay()],
            default      => [$now->copy()->startOfMonth(), $now->copy()->endOfDay()], // month-to-date
        };
    }

    private function periodLabel(string $period): string
    {
        return match ($period) {
            'today'      => 'hari ini',
            'yesterday'  => 'kemarin',
            'week'       => 'minggu ini',
            'last_month' => 'bulan lalu',
            'all'        => 'semua waktu',
            default      => 'bulan ini',
        };
    }

    private function perDayAverage(int $sum, Carbon $start, Carbon $end): int
    {
        $days = max(1, (int) $start->diffInDays($end) + 1);
        return (int) round($sum / $days);
    }

    /**
     * Fuzzy-match an AI-returned category string to a user's custom Category row.
     * Returns null if no match found — falls back to the text-based category column.
     */
    private function resolveCustomCategoryId(User $user, ?string $aiCategory): ?int
    {
        if (!$aiCategory) {
            return null;
        }

        $lower = strtolower(trim($aiCategory));

        // Exact name match first
        $match = Category::forUser($user->id)
            ->whereRaw('LOWER(name) = ?', [$lower])
            ->first();

        if ($match) {
            return $match->id;
        }

        // Partial match: category name contains the AI label or vice-versa
        $categories = Category::forUser($user->id)->ordered()->get();
        foreach ($categories as $cat) {
            if (str_contains(strtolower($cat->name), $lower) || str_contains($lower, strtolower($cat->name))) {
                return $cat->id;
            }
        }

        return null;
    }

    private function mapIntentToType(string $intent): string
    {
        return match ($intent) {
            'log_expense' => 'expense',
            'log_meal' => 'meal',
            'log_saving' => 'saving',
            'log_income' => 'income',
            'log_bill_payment' => 'bill_payment',
            'log_debt_payment' => 'debt_payment',
            'log_sinking_deposit' => 'sinking_fund_deposit',
            'transfer_fund' => 'transfer',
            default => 'expense',
        };
    }
}
