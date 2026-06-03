<?php

namespace App\Http\Controllers;

use App\Models\BehavioralMemory;
use App\Models\Entry;
use App\Models\User;
use App\Services\BehavioralMemoryService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\URL;
use Illuminate\View\View;

class DashboardController extends Controller
{
    // ── Auth ──────────────────────────────────────────────────────────────

    /**
     * Signed URL entry — creates 30-minute sliding session.
     */
    public function auth(Request $request, int $telegram_id): RedirectResponse
    {
        // 'signed' middleware validated the URL
        $user = User::where('telegram_chat_id', $telegram_id)
                    ->whereNotNull('onboarding_complete_at')
                    ->firstOrFail();

        session([
            'dashboard_user_id' => $user->id,
            'dashboard_expires'  => now()->addMinutes(30)->timestamp,
        ]);

        return redirect()->route('dashboard.index');
    }

    // ── Pages ──────────────────────────────────────────────────────────────

    public function index(Request $request): View
    {
        $user = $request->dashboard_user;

        // ── Today ──────────────────────────────────────────────────
        $todaySpend    = Entry::where('user_id', $user->id)->where('type', 'expense')
                              ->whereNotNull('confirmed_at')->where('is_undone', false)
                              ->whereDate('entry_time', today())->sum('amount');

        $todayCalories = Entry::where('user_id', $user->id)->where('type', 'meal')
                              ->whereNotNull('confirmed_at')->where('is_undone', false)
                              ->whereDate('entry_time', today())->sum('calories');

        $todayIncome   = Entry::where('user_id', $user->id)->where('type', 'income')
                              ->whereNotNull('confirmed_at')->where('is_undone', false)
                              ->whereDate('entry_time', today())->sum('amount');

        // ── Month ──────────────────────────────────────────────────
        $monthlySpend  = Entry::where('user_id', $user->id)->where('type', 'expense')
                              ->whereNotNull('confirmed_at')->where('is_undone', false)
                              ->whereMonth('entry_time', today()->month)
                              ->whereYear('entry_time', today()->year)->sum('amount');

        $monthlyIncome = Entry::where('user_id', $user->id)->where('type', 'income')
                              ->whereNotNull('confirmed_at')->where('is_undone', false)
                              ->whereMonth('entry_time', today()->month)
                              ->whereYear('entry_time', today()->year)->sum('amount');

        $monthlySavings = Entry::where('user_id', $user->id)
                               ->whereIn('type', ['saving', 'sinking_fund_deposit'])
                               ->whereNotNull('confirmed_at')->where('is_undone', false)
                               ->whereMonth('entry_time', today()->month)
                               ->whereYear('entry_time', today()->year)->sum('amount');

        // ── 7-day spending chart ───────────────────────────────────
        $spendingChart = Entry::where('user_id', $user->id)->where('type', 'expense')
            ->whereNotNull('confirmed_at')->where('is_undone', false)
            ->where('entry_time', '>=', now()->subDays(6)->startOfDay())
            ->selectRaw('DATE(entry_time) as day, SUM(amount) as total')
            ->groupBy('day')->orderBy('day')
            ->pluck('total', 'day')->toArray();

        // ── Category breakdown this month ──────────────────────────
        $categoryBreakdown = Entry::where('user_id', $user->id)->where('type', 'expense')
            ->whereNotNull('confirmed_at')->where('is_undone', false)
            ->whereMonth('entry_time', today()->month)->whereYear('entry_time', today()->year)
            ->selectRaw('category, SUM(amount) as total')
            ->groupBy('category')->orderByDesc('total')
            ->pluck('total', 'category')->toArray();

        // ── Streak ────────────────────────────────────────────────
        $streak = $user->streak;

        // ── Accounts & Funds ──────────────────────────────────────
        $accounts    = $user->accounts()->orderByDesc('is_default_spending')->get();
        $sinkingFunds = $user->funds()
            ->whereIn('fund_type', ['sinking_fund', 'goal', 'savings', 'emergency_fund'])
            ->where('is_active', true)
            ->with(['fundTransactions.entry.sourceFund'])->get();

        // ── Total balance ──────────────────────────────────────────
        // "Total Kekayaan" = liquid cash in spending accounts only.
        // Goals/sinking funds are earmarked money — shown separately.
        $totalAccountBalance = $user->accounts()->where('is_active', true)->sum('current_balance');
        $totalBalance        = $totalAccountBalance;   // spending accounts = net liquid balance
        $totalSavingsBalance = $user->funds()
            ->whereIn('fund_type', ['savings', 'emergency_fund', 'sinking_fund', 'goal'])
            ->where('is_active', true)->sum('current_balance');

        foreach ($sinkingFunds as $fund) {
            $breakdown = [];
            foreach ($fund->fundTransactions as $trx) {
                if ($trx->transaction_type === 'deposit') {
                    $src = $trx->entry?->sourceFund?->name ?? 'Lainnya';
                    $breakdown[$src] = ($breakdown[$src] ?? 0) + $trx->amount;
                }
            }
            $fund->breakdown = $breakdown;
        }

        // ── Bills due soon ────────────────────────────────────────
        $billsDue = $user->bills()->active()
            ->where('this_month_paid', false)
            ->orderBy('due_day')->get()
            ->filter(fn($b) => ($b->due_day - today()->day) <= 7 && ($b->due_day - today()->day) >= 0);

        // ── Recent activity ───────────────────────────────────────
        $recentActivities = Entry::where('user_id', $user->id)
            ->whereNotNull('confirmed_at')->where('is_undone', false)
            ->orderByDesc('entry_time')->take(6)->get();

        // ── Financial Health Score ────────────────────────────────
        $healthData = null;
        if ($user->isFinanceMode()) {
            try {
                $healthData = app(\App\Services\FinancialHealthService::class)->getOrCalculate($user);
            } catch (\Exception $e) {
                // Non-critical — fail silently
            }
        }

        return view('dashboard.index', compact(
            'user', 'todaySpend', 'todayCalories', 'todayIncome',
            'monthlySpend', 'monthlyIncome', 'monthlySavings',
            'spendingChart', 'categoryBreakdown',
            'streak', 'accounts', 'sinkingFunds', 'billsDue', 'recentActivities',
            'totalBalance', 'totalAccountBalance', 'totalSavingsBalance',
            'healthData'
        ));
    }

    public function history(Request $request): View|\Symfony\Component\HttpFoundation\StreamedResponse
    {
        $user = $request->dashboard_user;

        $query = Entry::with('fundTransactions.fund')
                      ->where('user_id', $user->id)
                      ->whereNotNull('confirmed_at')
                      ->where('is_undone', false);

        if ($request->filled('type')) {
            $query->where('type', $request->type);
        }
        if ($request->filled('from')) {
            $query->whereDate('entry_time', '>=', $request->from);
        }
        if ($request->filled('to')) {
            $query->whereDate('entry_time', '<=', $request->to);
        }
        if ($request->filled('min_amount')) {
            $query->where('amount', '>=', (int) $request->min_amount);
        }
        if ($request->filled('max_amount')) {
            $query->where('amount', '<=', (int) $request->max_amount);
        }
        if ($request->filled('fund_id')) {
            $query->where('source_fund_id', $request->fund_id);
        }

        // ── CSV export ──────────────────────────────────────────────────
        if ($request->get('export') === 'csv') {
            $allEntries = (clone $query)->orderByDesc('entry_time')->get();

            $headers = [
                'Content-Type'        => 'text/csv; charset=UTF-8',
                'Content-Disposition' => 'attachment; filename="butler-history-' . now()->format('Ymd') . '.csv"',
            ];

            return response()->streamDownload(function () use ($allEntries) {
                $out = fopen('php://output', 'w');
                fputcsv($out, ['Tanggal', 'Tipe', 'Keterangan', 'Kategori', 'Jumlah (Rp)', 'Kalori', 'Akun']);
                foreach ($allEntries as $e) {
                    fputcsv($out, [
                        $e->entry_time->format('d/m/Y H:i'),
                        $e->type,
                        $e->food_item ?? $e->merchant ?? $e->note ?? '',
                        $e->category ?? '',
                        $e->amount ?? '',
                        $e->calories ?? '',
                        $e->fundTransactions->first()?->fund?->name ?? '',
                    ]);
                }
                fclose($out);
            }, 'butler-history-' . now()->format('Ymd') . '.csv', $headers);
        }

        $entries = $query->orderByDesc('entry_time')->paginate(20)->withQueryString();

        // Funds for filter dropdown
        $funds = $user->funds()->where('is_active', true)->orderBy('name')->get(['id', 'name']);

        return view('dashboard.history', compact('user', 'entries', 'funds'));
    }

    public function spending(Request $request): View
    {
        $user = $request->dashboard_user;

        $monthStart = today()->startOfMonth();
        $monthEnd   = today()->endOfMonth();

        $monthSpending = Entry::where('user_id', $user->id)
            ->where('type', 'expense')
            ->whereNotNull('confirmed_at')
            ->where('is_undone', false)
            ->whereBetween('entry_time', [$monthStart, $monthEnd])
            ->sum('amount');

        $monthSavings = Entry::where('user_id', $user->id)
            ->whereIn('type', ['saving', 'sinking_fund_deposit'])
            ->whereNotNull('confirmed_at')
            ->where('is_undone', false)
            ->whereBetween('entry_time', [$monthStart, $monthEnd])
            ->sum('amount');

        $totalSavings = Entry::where('user_id', $user->id)
            ->whereIn('type', ['saving', 'sinking_fund_deposit'])
            ->whereNotNull('confirmed_at')
            ->where('is_undone', false)
            ->sum('amount');

        $monthBudget = ($user->monthly_budget_idr ?? 0) ?: (($user->daily_budget_idr ?? 0) * 30);

        // Daily spending for last 30 days  ['YYYY-MM-DD' => amount]
        $dailyRows = Entry::where('user_id', $user->id)
            ->where('type', 'expense')
            ->whereNotNull('confirmed_at')
            ->where('is_undone', false)
            ->where('entry_time', '>=', now()->subDays(29)->startOfDay())
            ->selectRaw('DATE(entry_time) as day, SUM(amount) as total')
            ->groupBy('day')
            ->orderBy('day')
            ->pluck('total', 'day')
            ->toArray();

        // Category breakdown this month
        $categoryRows = Entry::where('user_id', $user->id)
            ->where('type', 'expense')
            ->whereNotNull('confirmed_at')
            ->where('is_undone', false)
            ->whereBetween('entry_time', [$monthStart, $monthEnd])
            ->selectRaw('category, SUM(amount) as total')
            ->groupBy('category')
            ->orderByDesc('total')
            ->pluck('total', 'category')
            ->toArray();

        $transactions = Entry::where('user_id', $user->id)
            ->where('type', 'expense')
            ->whereNotNull('confirmed_at')
            ->where('is_undone', false)
            ->orderByDesc('entry_time')
            ->paginate(20);

        $dailySpending     = $dailyRows;
        $categoryBreakdown = $categoryRows;

        // Week-over-week comparison
        $thisWeekSpend = Entry::where('user_id', $user->id)
            ->where('type', 'expense')->whereNotNull('confirmed_at')->where('is_undone', false)
            ->whereBetween('entry_time', [today()->startOfWeek(), today()->endOfWeek()])
            ->sum('amount');

        $lastWeekSpend = Entry::where('user_id', $user->id)
            ->where('type', 'expense')->whereNotNull('confirmed_at')->where('is_undone', false)
            ->whereBetween('entry_time', [today()->subWeek()->startOfWeek(), today()->subWeek()->endOfWeek()])
            ->sum('amount');

        $wowChangePct = $lastWeekSpend > 0
            ? round((($thisWeekSpend - $lastWeekSpend) / $lastWeekSpend) * 100)
            : null;

        return view('dashboard.spending', compact(
            'user', 'monthSpending', 'monthBudget', 'monthSavings',
            'totalSavings', 'dailySpending', 'categoryBreakdown', 'transactions',
            'thisWeekSpend', 'lastWeekSpend', 'wowChangePct'
        ));
    }

    public function nutrition(Request $request): View
    {
        $user = $request->dashboard_user;

        $calorieGoal = $user->daily_calorie_goal ?? 2000;

        $todayMeals = Entry::where('user_id', $user->id)
            ->where('type', 'meal')
            ->whereNotNull('confirmed_at')
            ->where('is_undone', false)
            ->whereDate('entry_time', today())
            ->orderBy('entry_time')
            ->get();

        $todayCalories = $todayMeals->sum('calories');

        $macros = $todayMeals->reduce(function ($carry, $meal) {
            return [
                'protein' => round($carry['protein'] + ($meal->protein_g ?? 0), 1),
                'carbs'   => round($carry['carbs']   + ($meal->carbs_g   ?? 0), 1),
                'fat'     => round($carry['fat']     + ($meal->fat_g     ?? 0), 1),
            ];
        }, ['protein' => 0, 'carbs' => 0, 'fat' => 0]);

        $todayMacros = array_merge(['calories' => $todayCalories], $macros);

        // 7-day calorie average
        $last7 = Entry::where('user_id', $user->id)
            ->where('type', 'meal')
            ->whereNotNull('confirmed_at')
            ->where('is_undone', false)
            ->where('entry_time', '>=', now()->subDays(6)->startOfDay())
            ->selectRaw('DATE(entry_time) as day, SUM(calories) as total')
            ->groupBy('day')
            ->pluck('total', 'day')
            ->toArray();

        $avgCalories = count($last7) > 0 ? (int) (array_sum($last7) / count($last7)) : 0;

        // Daily calories for last 30 days
        $dailyCalories = Entry::where('user_id', $user->id)
            ->where('type', 'meal')
            ->whereNotNull('confirmed_at')
            ->where('is_undone', false)
            ->where('entry_time', '>=', now()->subDays(29)->startOfDay())
            ->selectRaw('DATE(entry_time) as day, SUM(calories) as total')
            ->groupBy('day')
            ->orderBy('day')
            ->pluck('total', 'day')
            ->toArray();

        $recentMeals = Entry::where('user_id', $user->id)
            ->where('type', 'meal')
            ->whereNotNull('confirmed_at')
            ->where('is_undone', false)
            ->orderByDesc('entry_time')
            ->take(30)
            ->get();

        return view('dashboard.nutrition', compact(
            'user', 'calorieGoal', 'todayMeals', 'todayMacros',
            'avgCalories', 'dailyCalories', 'recentMeals'
        ));
    }

    public function insights(Request $request): View
    {
        $user = $request->dashboard_user;

        $calorieGoal = $user->daily_calorie_goal ?? 2000;
        $monthBudget = ($user->monthly_budget_idr ?? 0) ?: (($user->daily_budget_idr ?? 0) * 30);

        // Daily spending last 30 days
        $dailySpending = Entry::where('user_id', $user->id)
            ->where('type', 'expense')
            ->whereNotNull('confirmed_at')
            ->where('is_undone', false)
            ->where('entry_time', '>=', now()->subDays(29)->startOfDay())
            ->selectRaw('DATE(entry_time) as day, SUM(amount) as total')
            ->groupBy('day')
            ->orderBy('day')
            ->pluck('total', 'day')
            ->toArray();

        // Daily calories last 30 days
        $dailyCalories = Entry::where('user_id', $user->id)
            ->where('type', 'meal')
            ->whereNotNull('confirmed_at')
            ->where('is_undone', false)
            ->where('entry_time', '>=', now()->subDays(29)->startOfDay())
            ->selectRaw('DATE(entry_time) as day, SUM(calories) as total')
            ->groupBy('day')
            ->orderBy('day')
            ->pluck('total', 'day')
            ->toArray();

        // Category breakdown this month
        $categoryBreakdown = Entry::where('user_id', $user->id)
            ->where('type', 'expense')
            ->whereNotNull('confirmed_at')
            ->where('is_undone', false)
            ->whereMonth('entry_time', today()->month)
            ->whereYear('entry_time', today()->year)
            ->selectRaw('category, SUM(amount) as total')
            ->groupBy('category')
            ->orderByDesc('total')
            ->pluck('total', 'category')
            ->toArray();

        // Mood history (table may not exist — guard with try/catch)
        $moodHistory = [];
        try {
            $moodHistory = DB::table('mood_logs')
                ->where('user_id', $user->id)
                ->orderByDesc('log_date')
                ->take(30)
                ->get(['log_date as date', 'mood_score', 'energy'])
                ->toArray();
        } catch (\Exception) {
            // mood_logs table not available — leave empty
        }

        return view('dashboard.insights', compact(
            'user', 'dailySpending', 'dailyCalories',
            'calorieGoal', 'monthBudget', 'categoryBreakdown', 'moodHistory'
        ));
    }

    // ── Finance: Distribution ────────────────────────────────────────────────

    public function distribution(Request $request): View
    {
        $user = $request->dashboard_user;

        $accounts = $user->accounts()->where('is_active', true)->get();

        // Group by account_type (from metadata)
        $byType = [];
        foreach ($accounts as $account) {
            $type = $account->type ?? 'other';
            if (!isset($byType[$type])) {
                $byType[$type] = ['label' => $this->accountTypeLabel($type), 'balance' => 0, 'accounts' => []];
            }
            $byType[$type]['balance'] += $account->current_balance;
            $byType[$type]['accounts'][] = $account;
        }

        $totalBalance = $accounts->sum('current_balance');

        // This month spending by account
        $monthStart = today()->startOfMonth();
        $monthEnd = today()->endOfMonth();

        $spendingByAccount = Entry::where('user_id', $user->id)
            ->where('type', 'expense')
            ->whereNotNull('confirmed_at')->where('is_undone', false)
            ->whereBetween('entry_time', [$monthStart, $monthEnd])
            ->whereNotNull('source_fund_id')
            ->selectRaw('source_fund_id, SUM(amount) as total, COUNT(*) as tx_count')
            ->groupBy('source_fund_id')
            ->get()
            ->keyBy('source_fund_id');

        // Per-account transaction counts and spending
        foreach ($accounts as $account) {
            $data = $spendingByAccount->get($account->id);
            $account->month_spending = $data?->total ?? 0;
            $account->month_tx_count = $data?->tx_count ?? 0;
        }

        // Spending by type
        $spendingByType = [];
        foreach ($accounts as $account) {
            $type = $account->type ?? 'other';
            $spendingByType[$type] = ($spendingByType[$type] ?? 0) + $account->month_spending;
        }

        return view('dashboard.distribution', compact('user', 'accounts', 'byType', 'totalBalance', 'spendingByType'));
    }

    // ── Finance: Cashflow ─────────────────────────────────────────────────

    public function cashflow(Request $request): View
    {
        $user = $request->dashboard_user;

        // Monthly aggregates (last 6 months)
        $months = [];
        for ($i = 5; $i >= 0; $i--) {
            $date = today()->subMonths($i);
            $key = $date->format('Y-m');
            $label = $date->translatedFormat('M Y');

            $income = Entry::where('user_id', $user->id)->where('type', 'income')
                ->whereNotNull('confirmed_at')->where('is_undone', false)
                ->whereMonth('entry_time', $date->month)->whereYear('entry_time', $date->year)
                ->sum('amount');

            $expenses = Entry::where('user_id', $user->id)->where('type', 'expense')
                ->whereNotNull('confirmed_at')->where('is_undone', false)
                ->whereMonth('entry_time', $date->month)->whereYear('entry_time', $date->year)
                ->sum('amount');

            $savings = Entry::where('user_id', $user->id)
                ->whereIn('type', ['saving', 'sinking_fund_deposit'])
                ->whereNotNull('confirmed_at')->where('is_undone', false)
                ->whereMonth('entry_time', $date->month)->whereYear('entry_time', $date->year)
                ->sum('amount');

            $months[$key] = [
                'label' => $label,
                'income' => (int) $income,
                'expenses' => (int) $expenses,
                'savings' => (int) $savings,
                'net' => (int) ($income - $expenses),
            ];
        }

        // Cumulative savings growth
        $cumulativeSavings = [];
        $runningTotal = 0;
        foreach ($months as $key => $m) {
            $runningTotal += $m['savings'];
            $cumulativeSavings[$key] = $runningTotal;
        }

        // Current fund balances for savings total
        $totalSavingsBalance = $user->funds()
            ->whereIn('fund_type', ['savings', 'emergency_fund', 'sinking_fund'])
            ->where('is_active', true)->sum('current_balance');

        // Cashflow health score
        $currentMonth = $months[today()->format('Y-m')] ?? end($months);
        $healthScore = 0;
        if ($currentMonth['income'] > 0) {
            $savingsRate = max(0, $currentMonth['net']) / $currentMonth['income'];
            $healthScore = min(100, (int) ($savingsRate * 200)); // 50% savings rate = 100
        }

        // Expense ratio
        $expenseRatio = $currentMonth['income'] > 0
            ? round(($currentMonth['expenses'] / $currentMonth['income']) * 100)
            : 0;

        // Best/worst month
        $bestMonth = null;
        $worstMonth = null;
        foreach ($months as $key => $m) {
            if ($bestMonth === null || $m['net'] > $months[$bestMonth]['net']) $bestMonth = $key;
            if ($worstMonth === null || $m['net'] < $months[$worstMonth]['net']) $worstMonth = $key;
        }

        // Composite Financial Health Score (from FinancialHealthService)
        $healthData = null;
        try {
            $healthData = app(\App\Services\FinancialHealthService::class)->getOrCalculate($user);
        } catch (\Exception $e) { /* non-critical */ }

        return view('dashboard.cashflow', compact(
            'user', 'months', 'cumulativeSavings', 'totalSavingsBalance',
            'healthScore', 'expenseRatio', 'bestMonth', 'worstMonth', 'healthData'
        ));
    }

    // ── Finance: Timeline ─────────────────────────────────────────────────

    public function timeline(Request $request): View
    {
        $user = $request->dashboard_user;

        $date = $request->date('date') ?? today();
        $tz = $user->timezone ?? 'Asia/Jakarta';

        // All entries for this day
        $entries = Entry::where('user_id', $user->id)
            ->whereNotNull('confirmed_at')->where('is_undone', false)
            ->whereDate('entry_time', $date)
            ->orderBy('entry_time')
            ->get();

        // Calculate running balance
        $runningBalance = 0;
        $totalIn = 0;
        $totalOut = 0;
        foreach ($entries as $entry) {
            if (in_array($entry->type, ['income'])) {
                $runningBalance += $entry->amount;
                $totalIn += $entry->amount;
            } elseif (in_array($entry->type, ['expense', 'bill_payment', 'debt_payment'])) {
                $runningBalance -= $entry->amount;
                $totalOut += $entry->amount;
            } elseif (in_array($entry->type, ['saving', 'sinking_fund_deposit'])) {
                $runningBalance -= $entry->amount; // outflow from spending
                $totalOut += $entry->amount;
            }
            $entry->running_balance = $runningBalance;
        }

        // Monthly heatmap: spending per day for the current month
        $monthStart = $date->copy()->startOfMonth();
        $monthEnd = $date->copy()->endOfMonth();

        $dailyTotals = Entry::where('user_id', $user->id)
            ->where('type', 'expense')
            ->whereNotNull('confirmed_at')->where('is_undone', false)
            ->whereBetween('entry_time', [$monthStart, $monthEnd])
            ->selectRaw('DATE(entry_time) as day, SUM(amount) as total')
            ->groupBy('day')
            ->pluck('total', 'day')
            ->toArray();

        $maxDaily = max(array_values($dailyTotals) ?: [0]);

        return view('dashboard.timeline', compact(
            'user', 'date', 'entries', 'totalIn', 'totalOut',
            'dailyTotals', 'maxDaily', 'monthStart', 'monthEnd'
        ));
    }

    // ── Finance: Debt Manager ─────────────────────────────────────────────

    public function debtManager(Request $request): View
    {
        $user = $request->dashboard_user;

        $debts = $user->debts()->active()->orderByDesc('interest_rate')->get();
        $allDebts = $user->debts()->get(); // include paid off
        $bills = $user->bills()->active()->orderBy('due_day')->get();

        // Total metrics
        $totalDebt = $debts->sum('remaining_balance');
        $monthlyObligations = $debts->sum('monthly_installment') + $bills->sum('amount');
        $totalOriginal = $debts->sum('total_amount');

        // Payoff projections per debt
        foreach ($debts as $debt) {
            if ($debt->monthly_installment > 0) {
                $monthsRemaining = (int) ceil($debt->remaining_balance / $debt->monthly_installment);
                $debt->months_remaining = $monthsRemaining;
                $debt->projected_payoff = today()->addMonths($monthsRemaining)->format('M Y');
                $debt->progress_pct = $debt->total_amount > 0
                    ? round((1 - ($debt->remaining_balance / $debt->total_amount)) * 100)
                    : 0;
            } else {
                $debt->months_remaining = null;
                $debt->projected_payoff = null;
                $debt->progress_pct = 0;
            }
        }

        // Overall projected payoff (longest)
        $maxMonths = $debts->max('months_remaining');
        $overallPayoff = $maxMonths ? today()->addMonths((int) $maxMonths)->format('M Y') : null;

        // Bills this month status
        $billsPaid = $bills->where('this_month_paid', true)->count();
        $billsTotal = $bills->count();

        // Debt composition by type
        $debtByType = $debts->groupBy('debt_type')->map(fn($group) => $group->sum('remaining_balance'));

        return view('dashboard.debts', compact(
            'user', 'debts', 'bills', 'totalDebt', 'monthlyObligations',
            'overallPayoff', 'billsPaid', 'billsTotal', 'debtByType'
        ));
    }

    private function accountTypeLabel(string $type): string
    {
        return match ($type) {
            'bank' => 'Bank',
            'ewallet' => 'E-Wallet',
            'emoney' => 'E-Money',
            'cash' => 'Cash',
            'credit_card' => 'Kartu Kredit',
            default => 'Lainnya',
        };
    }

    // ── API: Entry Edit (with Telegram Undo conflict guard) ───────────────

    /**
     * Update an entry from the dashboard.
     * Blocks edits while the Telegram undo window is still open.
     */
    public function updateEntry(Request $request, Entry $entry): JsonResponse
    {
        $user = $request->dashboard_user;

        // Gate: entry must belong to current dashboard user
        abort_if($entry->user_id !== $user->id, 403);

        // Conflict guard: if within the Telegram undo window → reject
        if ($entry->undo_expires_at && now()->lt($entry->undo_expires_at)) {
            return response()->json([
                'error' => 'Transaksi ini masih bisa di-undo dari Telegram. Tunggu beberapa menit.',
            ], 409);
        }

        $validated = $request->validate([
            'amount'    => 'nullable|integer|min:1',
            'category'  => 'nullable|string|max:32',
            'note'      => 'nullable|string|max:255',
            'merchant'  => 'nullable|string|max:128',
            // Meal-specific fields
            'calories'  => 'nullable|integer|min:0',
            'food_item' => 'nullable|string|max:256',
        ]);

        // When calories are user-corrected, mark as no longer estimated
        // AND feed the correction back into behavioral memory (food_calories domain)
        if (isset($validated['calories'])) {
            $validated['is_calorie_estimated'] = false;

            $foodItem = $validated['food_item'] ?? $entry->food_item;
            if ($foodItem && $validated['calories'] > 0) {
                $memory = app(BehavioralMemoryService::class);
                $memory->observe($user->id, BehavioralMemory::DOMAIN_FOOD_CALORIES, $foodItem, [
                    'calories'   => (int) $validated['calories'],
                    'source'     => 'user_correction',
                    'corrected_at' => now()->toISOString(),
                ]);
            }
        }

        $entry->update(array_filter($validated, fn ($v) => $v !== null));

        return response()->json(['ok' => true]);
    }

    public function memory(Request $request): View
    {
        $user = $request->dashboard_user;

        $memories = BehavioralMemory::forUser($user->id)
            ->orderByDesc('behavioral_confidence')
            ->orderByDesc('observation_count')
            ->get()
            ->groupBy('domain');

        $domainLabels = [
            BehavioralMemory::DOMAIN_MERCHANT_ACCOUNT  => '🏪 Merchant → Akun',
            BehavioralMemory::DOMAIN_FOOD_CALORIES     => '🍽️ Kalori Makanan',
            BehavioralMemory::DOMAIN_FOOD_PORTION      => '🥗 Porsi Makanan',
            BehavioralMemory::DOMAIN_MEAL_TIMING       => '⏰ Waktu Makan',
            BehavioralMemory::DOMAIN_CATEGORY_ACCOUNT  => '📂 Kategori → Akun',
            BehavioralMemory::DOMAIN_SPEND_RHYTHM      => '📈 Pola Pengeluaran',
        ];

        return view('dashboard.memory', compact('user', 'memories', 'domainLabels'));
    }

    public function deleteMemory(Request $request, BehavioralMemory $memory): \Illuminate\Http\JsonResponse
    {
        abort_if($memory->user_id !== $request->dashboard_user->id, 403);
        $memory->delete();
        return response()->json(['ok' => true]);
    }

    public function settings(Request $request): View
    {
        $user           = $request->dashboard_user;
        $userCategories = \App\Models\Category::forUser($user->id)->ordered()->get();

        return view('dashboard.settings', [
            'user'           => $user,
            'userCategories' => $userCategories,
        ]);
    }

    public function saveSettings(Request $request): RedirectResponse
    {
        $user = $request->dashboard_user;

        $validated = $request->validate([
            'name'                  => 'required|string|max:80',
            'timezone'              => 'required|string|max:60',
            'currency'              => 'nullable|string|max:10',
            'tracking_mode'         => 'required|in:finance,calorie,both',
            'daily_budget_idr'      => 'nullable|integer|min:0',
            'monthly_budget_idr'    => 'nullable|integer|min:0',
            'monthly_income_idr'    => 'nullable|integer|min:0',
            'daily_calorie_goal'    => 'nullable|integer|min:0',
            'calorie_goal_type'     => 'nullable|in:bulking,cutting,maintenance',
            'daily_summary_enabled' => 'nullable|boolean',
            'summary_time'          => 'nullable|string|regex:/^\d{2}:\d{2}$/',
        ]);

        $validated['daily_summary_enabled'] = $request->boolean('daily_summary_enabled');

        $user->update($validated);

        return redirect()->route('dashboard.settings')->with('success', 'Pengaturan disimpan!');
    }

    // ── Dashboard Signed URL Generator (called from TelegramWebhookController) ───

    public static function generateSignedUrl(User $user): string
    {
        return URL::temporarySignedRoute(
            'dashboard.auth',
            now()->addMinutes(30),
            ['telegram_id' => $user->telegram_chat_id]
        );
    }
}
