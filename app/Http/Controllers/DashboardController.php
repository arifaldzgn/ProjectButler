<?php

namespace App\Http\Controllers;

use App\Models\Entry;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
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

        $todaySpend   = Entry::where('user_id', $user->id)
                             ->where('type', 'expense')
                             ->whereNotNull('confirmed_at')
                             ->where('is_undone', false)
                             ->whereDate('entry_time', today())
                             ->sum('amount');

        $todayCalories = Entry::where('user_id', $user->id)
                              ->where('type', 'meal')
                              ->whereNotNull('confirmed_at')
                              ->where('is_undone', false)
                              ->whereDate('entry_time', today())
                              ->sum('calories');

        // Monthly Stats
        $monthlySpend = Entry::where('user_id', $user->id)
                             ->where('type', 'expense')
                             ->whereNotNull('confirmed_at')
                             ->where('is_undone', false)
                             ->whereMonth('entry_time', today()->month)
                             ->whereYear('entry_time', today()->year)
                             ->sum('amount');
                             
        $monthlyIncome = Entry::where('user_id', $user->id)
                             ->where('type', 'income')
                             ->whereNotNull('confirmed_at')
                             ->where('is_undone', false)
                             ->whereMonth('entry_time', today()->month)
                             ->whereYear('entry_time', today()->year)
                             ->sum('amount');

        $accounts = $user->accounts()->orderByDesc('is_default_spending')->get();
        $sinkingFunds = $user->funds()
                             ->whereIn('fund_type', ['sinking_fund', 'goal', 'savings'])
                             ->where('is_active', true)
                             ->with(['fundTransactions.entry.sourceFund'])
                             ->get();
                             
        foreach ($sinkingFunds as $fund) {
            $breakdown = [];
            foreach ($fund->fundTransactions as $trx) {
                if ($trx->transaction_type === 'deposit') {
                    $sourceName = $trx->entry?->sourceFund?->name ?? 'Lainnya';
                    if (!isset($breakdown[$sourceName])) {
                        $breakdown[$sourceName] = 0;
                    }
                    $breakdown[$sourceName] += $trx->amount;
                }
            }
            $fund->breakdown = $breakdown;
        }

        $recentActivities = Entry::with('fundTransactions.fund')
                                 ->where('user_id', $user->id)
                                 ->whereNotNull('confirmed_at')
                                 ->where('is_undone', false)
                                 ->orderByDesc('entry_time')
                                 ->take(5)
                                 ->get();

        return view('dashboard.index', compact(
            'user', 
            'todaySpend', 
            'todayCalories', 
            'accounts',
            'monthlySpend',
            'monthlyIncome',
            'sinkingFunds',
            'recentActivities'
        ));
    }

    public function history(Request $request): View
    {
        $user = $request->dashboard_user;

        $query = Entry::with('fundTransactions.fund')
                      ->where('user_id', $user->id)
                      ->whereNotNull('confirmed_at')
                      ->where('is_undone', false);

        if ($request->has('type') && $request->type) {
            $query->where('type', $request->type);
        }
        if ($request->has('from') && $request->from) {
            $query->whereDate('entry_time', '>=', $request->from);
        }
        if ($request->has('to') && $request->to) {
            $query->whereDate('entry_time', '<=', $request->to);
        }

        $entries = $query->orderByDesc('entry_time')->paginate(20)->withQueryString();

        return view('dashboard.history', compact('user', 'entries'));
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
            'amount'   => 'nullable|integer|min:1',
            'category' => 'nullable|string|max:32',
            'note'     => 'nullable|string|max:255',
            'merchant' => 'nullable|string|max:128',
        ]);

        $entry->update(array_filter($validated, fn ($v) => $v !== null));

        return response()->json(['ok' => true]);
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
