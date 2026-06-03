<?php

namespace App\Http\Controllers;

use App\Models\AiLog;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AdminController extends Controller
{
    public function index()
    {
        $users = User::withCount(['entries', 'funds'])->orderByDesc('created_at')->paginate(50);
        return view('admin.users.index', compact('users'));
    }

    public function aiLogs(Request $request)
    {
        $query = AiLog::with('user')
            ->orderByDesc('created_at');

        // Filters
        if ($request->filled('call_type')) {
            $query->where('call_type', $request->call_type);
        }
        if ($request->filled('success')) {
            $query->where('was_successful', $request->success === '1');
        }
        if ($request->filled('user_id')) {
            $query->where('user_id', $request->user_id);
        }

        $logs      = $query->paginate(50)->withQueryString();
        $callTypes = AiLog::distinct()->pluck('call_type')->sort()->values();
        $users     = User::orderBy('name')->get(['id', 'name']);

        // Quick stats for the header
        $stats = [
            'total_today'      => AiLog::whereDate('created_at', today())->count(),
            'failures_today'   => AiLog::whereDate('created_at', today())->where('was_successful', false)->count(),
            'avg_latency_ms'   => (int) AiLog::whereDate('created_at', today())->avg('latency_ms'),
            'avg_confidence'   => round((float) AiLog::whereDate('created_at', today())->whereNotNull('confidence_score')->avg('confidence_score'), 2),
        ];

        // Per-intent failure rate (last 7 days, parse calls only)
        $intentBreakdown = DB::table('ai_logs')
            ->where('call_type', 'parse')
            ->where('created_at', '>=', now()->subDays(7))
            ->whereNotNull('intent_detected')
            ->where('intent_detected', '!=', 'unrecognized')
            ->selectRaw('intent_detected as intent, COUNT(*) as total,
                         SUM(CASE WHEN was_successful = 0 THEN 1 ELSE 0 END) as failures,
                         ROUND(AVG(confidence_score), 2) as avg_conf,
                         ROUND(AVG(latency_ms)) as avg_latency')
            ->groupBy('intent_detected')
            ->orderByDesc('total')
            ->get();

        return view('admin.ai-logs.index', compact('logs', 'callTypes', 'users', 'stats', 'intentBreakdown'));
    }

    /**
     * Grouped view of "unrecognized" Telegram messages — patterns of phrases
     * that Butler's parser/router couldn't handle, so we can see what users
     * are reaching for.
     */
    public function unrecognized(Request $request)
    {
        // Filters
        $from    = $request->date('from') ?: now()->subDays(30)->startOfDay();
        $to      = $request->date('to')   ?: now()->endOfDay();
        $minCnt  = (int) ($request->input('min_count', 1));

        // ── Grouped phrases ─────────────────────────────────────────────
        // Normalize via SQL: lowercase + collapse whitespace via TRIM on REGEX_REPLACE
        // (works on MySQL 8 / MariaDB 10.0+ — sufficient for this admin tool)
        $normalizedExpr = "TRIM(LOWER(raw_input))";

        $grouped = DB::table('ai_logs')
            ->where('intent_detected', 'unrecognized')
            ->whereBetween('created_at', [$from, $to])
            ->selectRaw("
                {$normalizedExpr} as phrase,
                COUNT(*) as occurrences,
                COUNT(DISTINCT user_id) as unique_users,
                MAX(created_at) as last_seen,
                MAX(error_message) as sample_suggestion,
                MAX(confidence_score) as max_confidence
            ")
            ->groupBy(DB::raw($normalizedExpr))
            ->havingRaw('COUNT(*) >= ?', [$minCnt])
            ->orderByDesc('occurrences')
            ->orderByDesc('last_seen')
            ->paginate(50)
            ->withQueryString();

        // ── Header stats (last 7 days) ──────────────────────────────────
        $stats = [
            'total_7d'         => AiLog::where('intent_detected', 'unrecognized')
                                       ->where('created_at', '>=', now()->subDays(7))->count(),
            'unique_phrases_7d'=> (int) DB::table('ai_logs')
                                       ->where('intent_detected', 'unrecognized')
                                       ->where('created_at', '>=', now()->subDays(7))
                                       ->distinct(DB::raw($normalizedExpr))
                                       ->count(DB::raw($normalizedExpr)),
            'unique_users_7d'  => (int) AiLog::where('intent_detected', 'unrecognized')
                                       ->where('created_at', '>=', now()->subDays(7))
                                       ->distinct('user_id')
                                       ->count('user_id'),
        ];

        // Top phrase (last 7d)
        $top = DB::table('ai_logs')
            ->where('intent_detected', 'unrecognized')
            ->where('created_at', '>=', now()->subDays(7))
            ->selectRaw("{$normalizedExpr} as phrase, COUNT(*) as c")
            ->groupBy(DB::raw($normalizedExpr))
            ->orderByDesc('c')
            ->first();
        $stats['top_phrase']       = $top->phrase ?? null;
        $stats['top_phrase_count'] = $top->c      ?? 0;

        // ── CSV export ──────────────────────────────────────────────────
        if ($request->get('export') === 'csv') {
            $allRows = DB::table('ai_logs')
                ->where('intent_detected', 'unrecognized')
                ->whereBetween('created_at', [$from, $to])
                ->selectRaw("
                    {$normalizedExpr} as phrase,
                    COUNT(*) as occurrences,
                    COUNT(DISTINCT user_id) as unique_users,
                    MAX(created_at) as last_seen,
                    MAX(error_message) as sample_suggestion
                ")
                ->groupBy(DB::raw($normalizedExpr))
                ->havingRaw('COUNT(*) >= ?', [$minCnt])
                ->orderByDesc('occurrences')
                ->get();

            return response()->streamDownload(function () use ($allRows) {
                $out = fopen('php://output', 'w');
                fputcsv($out, ['Phrase', 'Occurrences', 'Unique Users', 'Last Seen', 'Suggestion Kind', 'Suggestion Label']);
                foreach ($allRows as $row) {
                    $payload = json_decode($row->sample_suggestion ?? '{}', true);
                    $sugg    = $payload['suggestion'] ?? [];
                    fputcsv($out, [
                        $row->phrase,
                        $row->occurrences,
                        $row->unique_users,
                        $row->last_seen,
                        $sugg['kind'] ?? '',
                        $sugg['label'] ?? '',
                    ]);
                }
                fclose($out);
            }, 'unrecognized-' . now()->format('Ymd') . '.csv', [
                'Content-Type' => 'text/csv; charset=UTF-8',
            ]);
        }

        return view('admin.unrecognized.index', compact('grouped', 'stats', 'from', 'to', 'minCnt'));
    }

    /**
     * Token usage analytics — per-user breakdown of AI token consumption.
     */
    public function tokenUsage(Request $request)
    {
        $period = $request->input('period', '7d');
        $fromDate = match ($period) {
            '30d' => now()->subDays(30),
            'all' => null,
            default => now()->subDays(7),
        };

        // Per-user breakdown
        $query = DB::table('ai_logs')
            ->join('users', 'ai_logs.user_id', '=', 'users.id')
            ->select(
                'users.id as user_id',
                'users.name as user_name',
                DB::raw('COUNT(*) as total_calls'),
                DB::raw('COALESCE(SUM(token_count_input), 0) as total_input'),
                DB::raw('COALESCE(SUM(token_count_output), 0) as total_output'),
                DB::raw('COALESCE(SUM(token_count_input), 0) + COALESCE(SUM(token_count_output), 0) as total_tokens'),
                DB::raw('ROUND((COALESCE(SUM(token_count_input), 0) + COALESCE(SUM(token_count_output), 0)) / COUNT(*)) as avg_per_call')
            )
            ->whereNotNull('ai_logs.user_id');

        if ($fromDate) {
            $query->where('ai_logs.created_at', '>=', $fromDate);
        }

        $perUser = $query->groupBy('users.id', 'users.name')
            ->orderByDesc('total_tokens')
            ->get();

        // By call type
        $callTypeQuery = DB::table('ai_logs')
            ->select(
                'call_type',
                DB::raw('COUNT(*) as total_calls'),
                DB::raw('COALESCE(SUM(token_count_input), 0) as total_input'),
                DB::raw('COALESCE(SUM(token_count_output), 0) as total_output'),
                DB::raw('COALESCE(SUM(token_count_input), 0) + COALESCE(SUM(token_count_output), 0) as total_tokens')
            );

        if ($fromDate) {
            $callTypeQuery->where('created_at', '>=', $fromDate);
        }

        $byCallType = $callTypeQuery->groupBy('call_type')
            ->orderByDesc('total_tokens')
            ->get();

        // System totals
        $totalsQuery = DB::table('ai_logs');
        if ($fromDate) {
            $totalsQuery->where('created_at', '>=', $fromDate);
        }

        $systemTotals = $totalsQuery->selectRaw('
            COUNT(*) as total_calls,
            COALESCE(SUM(token_count_input), 0) as total_input,
            COALESCE(SUM(token_count_output), 0) as total_output,
            COALESCE(SUM(token_count_input), 0) + COALESCE(SUM(token_count_output), 0) as total_tokens,
            COUNT(DISTINCT user_id) as unique_users
        ')->first();

        // Estimate cost (Gemini 2.0 Flash: ~$0.10/1M input, ~$0.40/1M output)
        $estimatedCost = (($systemTotals->total_input * 0.10) + ($systemTotals->total_output * 0.40)) / 1_000_000;

        // Populated ratio — what % of recent logs have token data
        $recentQuery = DB::table('ai_logs');
        if ($fromDate) {
            $recentQuery->where('created_at', '>=', $fromDate);
        }
        $totalLogs = $recentQuery->count();
        $populatedLogs = (clone $recentQuery)->whereNotNull('token_count_input')->count();
        $populatedPct = $totalLogs > 0 ? round(($populatedLogs / $totalLogs) * 100) : 0;

        return view('admin.token-usage.index', compact(
            'perUser', 'byCallType', 'systemTotals', 'estimatedCost',
            'period', 'populatedPct'
        ));
    }

    public function impersonate(Request $request, User $user)
    {
        $admin = $request->dashboard_user;

        session([
            'admin_impersonator_id' => $admin->id,
            'dashboard_user_id' => $user->id,
            'dashboard_expires' => now()->addMinutes(120)->timestamp,
        ]);

        return redirect()->route('dashboard.index')->with('success', "You are now impersonating {$user->name}.");
    }

    public function leaveImpersonate(Request $request)
    {
        $adminId = session('admin_impersonator_id');

        if ($adminId) {
            session([
                'dashboard_user_id' => $adminId,
                'dashboard_expires' => now()->addMinutes(30)->timestamp,
            ]);
            session()->forget('admin_impersonator_id');
            return redirect()->route('admin.users.index')->with('success', 'Left impersonation.');
        }

        return redirect()->route('dashboard.index');
    }
}
