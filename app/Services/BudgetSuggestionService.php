<?php

namespace App\Services;

use App\Models\Entry;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

class BudgetSuggestionService
{
    private AIService $ai;
    private TelegramService $telegram;

    public function __construct(AIService $ai, TelegramService $telegram)
    {
        $this->ai = $ai;
        $this->telegram = $telegram;
    }

    /**
     * Generate and send an AI budget suggestion for a user based on last 30 days.
     * Can be called from a quick command or monthly scheduler.
     */
    public function sendSuggestion(User $user): bool
    {
        $context = $this->build30DayContext($user);

        if (!$context) {
            $this->telegram->sendMessage(
                (string) $user->telegram_chat_id,
                "Butler belum punya cukup data untuk kasih saran budget.\nCoba lagi setelah mencatat setidaknya 7 hari pengeluaran ya! 📊"
            );
            return false;
        }

        try {
            $suggestion = $this->ai->generateBudgetSuggestion($context);
        } catch (\Exception $e) {
            Log::error('Budget suggestion AI failed', ['user_id' => $user->id, 'error' => $e->getMessage()]);
            $suggestion = null;
        }

        if (!$suggestion) {
            $suggestion = $this->buildFallbackSuggestion($context);
        }

        $this->telegram->sendMessage(
            (string) $user->telegram_chat_id,
            "💡 *Saran Budget Butler*\n\n" . $suggestion
        );

        return true;
    }

    /**
     * Build a 30-day spending context for the AI prompt.
     * Returns null if insufficient data (fewer than 5 expense entries).
     */
    public function build30DayContext(User $user): ?array
    {
        $now   = Carbon::now($user->timezone);
        $since = $now->copy()->subDays(30)->startOfDay();

        $expenses = Entry::forUser($user->id)
            ->whereIn('type', ['expense', 'bill_payment'])
            ->confirmed()
            ->where('entry_time', '>=', $since)
            ->get(['amount', 'category', 'entry_time']);

        if ($expenses->count() < 5) {
            return null;
        }

        $totalSpent = $expenses->sum('amount');
        $income     = $user->monthly_income_idr ?? 0;

        // Category breakdown
        $byCategory = $expenses->groupBy('category')
            ->map(fn($g) => [
                'total'   => (int) $g->sum('amount'),
                'count'   => $g->count(),
                'pct'     => $totalSpent > 0 ? round($g->sum('amount') / $totalSpent * 100) : 0,
            ])
            ->sortByDesc('total')
            ->toArray();

        // Income-based ratios
        $expenseRatio = $income > 0 ? round($totalSpent / $income * 100) : null;

        // 50/30/20 reference
        $needs  = $income > 0 ? $income * 0.50 : null;
        $wants  = $income > 0 ? $income * 0.30 : null;
        $savingsTarget = $income > 0 ? $income * 0.20 : null;

        $savings = (int) Entry::forUser($user->id)
            ->where('type', 'saving')
            ->confirmed()
            ->where('entry_time', '>=', $since)
            ->sum('amount');

        return [
            'period_days'          => 30,
            'user' => [
                'name'               => $user->name,
                'monthly_income_idr' => $income,
                'daily_budget_idr'   => $user->daily_budget_idr,
            ],
            'totals' => [
                'spent_idr'     => $totalSpent,
                'saved_idr'     => $savings,
                'expense_ratio' => $expenseRatio,
            ],
            'category_breakdown' => $byCategory,
            'rule_5030_20' => [
                'needs_target_idr'   => $needs,
                'wants_target_idr'   => $wants,
                'savings_target_idr' => $savingsTarget,
            ],
        ];
    }

    private function buildFallbackSuggestion(array $context): string
    {
        $totalF  = 'Rp ' . number_format($context['totals']['spent_idr'], 0, ',', '.');
        $income  = $context['user']['monthly_income_idr'];
        $ratio   = $context['totals']['expense_ratio'];
        $topCat  = array_key_first($context['category_breakdown']);

        $msg = "30 hari terakhir kamu menghabiskan {$totalF}.";

        if ($ratio !== null) {
            $msg .= "\nRasio pengeluaran: {$ratio}% dari income.";
        }

        if ($topCat) {
            $topPct = $context['category_breakdown'][$topCat]['pct'];
            $msg .= "\nKategori terbesar: {$topCat} ({$topPct}%).";
        }

        if ($income > 0 && isset($context['rule_5030_20']['savings_target_idr'])) {
            $savTarget = 'Rp ' . number_format($context['rule_5030_20']['savings_target_idr'], 0, ',', '.');
            $msg .= "\n\n💡 Target tabungan ideal (20% income): {$savTarget}/bulan.";
        }

        return $msg;
    }
}
