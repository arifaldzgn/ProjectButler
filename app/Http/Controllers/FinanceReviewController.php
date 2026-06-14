<?php

namespace App\Http\Controllers;

use App\Models\FinanceReviewProfile;
use App\Services\FinanceReviewService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class FinanceReviewController extends Controller
{
    public function __construct(private readonly FinanceReviewService $service) {}

    public function show(Request $request): View|RedirectResponse
    {
        $user    = $request->dashboard_user;
        $profile = $this->service->getOrCreateProfile($user);

        if ($profile->isComplete()) {
            return redirect()->route('finance-review.result');
        }

        return $this->renderWizard($request);
    }

    /** GET /step/{n} — redirect to single-page wizard */
    public function step(Request $request, int $step): RedirectResponse
    {
        return redirect()->route('finance-review.index');
    }

    /** POST /step/{n} — save step, return JSON for AJAX or redirect for legacy */
    public function saveStep(Request $request, int $step): RedirectResponse|JsonResponse
    {
        $user    = $request->dashboard_user;
        $profile = $this->service->getOrCreateProfile($user);

        match ($step) {
            1 => $this->saveStep1($request, $profile),
            2 => $this->saveStep2($request, $profile),
            3 => $this->saveStep3($request, $profile),
            4 => $this->saveStep4($request, $profile),
            5 => $this->saveStep5($request, $profile),
            6 => $this->saveStep6($request, $profile),
            7 => $this->saveStep7($request, $profile),
            default => null,
        };

        $profile->last_completed_step = max($profile->last_completed_step, $step);

        if ($step === 7) {
            $profile->review_completed_at = now();
            $profile->save();

            $breakdown = $this->service->calculateBreakdown($profile);
            $insights  = $this->service->generateInsights($profile, $breakdown);
            $profile->update([
                'ai_insights'           => $insights,
                'insights_generated_at' => now(),
            ]);

            if ($request->wantsJson()) {
                return response()->json([
                    'success'  => true,
                    'redirect' => route('finance-review.result'),
                ]);
            }
            return redirect()->route('finance-review.result');
        }

        $profile->save();

        if ($request->wantsJson()) {
            return response()->json(['success' => true, 'next' => $step + 1]);
        }
        return redirect()->route('finance-review.index');
    }

    public function result(Request $request): View|RedirectResponse
    {
        $user    = $request->dashboard_user;
        $profile = FinanceReviewProfile::where('user_id', $user->id)->first();

        if (!$profile || !$profile->isComplete()) {
            return redirect()->route('finance-review.index');
        }

        $breakdown     = $this->service->calculateBreakdown($profile);
        $tangga        = $this->service->getTangga($breakdown);
        $ratioCards    = $this->service->getRatioCards($breakdown, $profile);
        $alternatifKota= $this->service->getAlternatifKota($profile, $breakdown);
        $umk           = $this->service->getUmkData($profile->domisili_key ?? '');

        return view('finance-review.result', compact(
            'profile', 'breakdown', 'tangga', 'ratioCards', 'alternatifKota', 'umk'
        ));
    }

    public function recalculate(Request $request): RedirectResponse
    {
        $user    = $request->dashboard_user;
        $profile = FinanceReviewProfile::where('user_id', $user->id)->firstOrFail();

        $breakdown = $this->service->calculateBreakdown($profile);
        $insights  = $this->service->generateInsights($profile, $breakdown);
        $profile->update([
            'ai_insights'           => $insights,
            'insights_generated_at' => now(),
        ]);

        return redirect()->route('finance-review.result')->with('success', 'Analisis diperbarui.');
    }

    public function reset(Request $request): RedirectResponse
    {
        $user = $request->dashboard_user;
        FinanceReviewProfile::where('user_id', $user->id)->delete();
        return redirect()->route('finance-review.index');
    }

    // ── Private helpers ────────────────────────────────────────────────────

    private function renderWizard(Request $request): View
    {
        $user    = $request->dashboard_user;
        $profile = $this->service->getOrCreateProfile($user);

        return view('finance-review.wizard', [
            'profile'       => $profile,
            'startStep'     => max(1, $profile->nextStep()),
            'incomeHint'    => $user->monthly_income_idr ?? null,
            'billsList'     => $profile->bills_snapshot     ?? $this->service->getAutoFilledBills($user),
            'recurringList' => $profile->recurring_snapshot ?? $this->service->getAutoFilledRecurring($user),
            'debtsList'     => $profile->debts_snapshot     ?? $this->service->getAutoFilledDebts($user),
        ]);
    }

    // ── Step savers ────────────────────────────────────────────────────────

    private function saveStep1(Request $request, FinanceReviewProfile $profile): void
    {
        $domisili    = trim($request->input('domisili', ''));
        $domisiliKey = FinanceReviewService::normalizeDomisili($domisili);

        $profile->fill([
            'domisili'     => $domisili,
            'domisili_key' => $domisiliKey,
            'gaji_bersih'  => $this->parseAmount($request->input('gaji_bersih')),
        ]);
    }

    private function saveStep2(Request $request, FinanceReviewProfile $profile): void
    {
        $profile->fill([
            'housing_status' => $request->input('housing_status'),
            'housing_cost'   => $request->input('housing_status') === 'ortu'
                                ? 0
                                : $this->parseAmount($request->input('housing_cost')),
        ]);
    }

    private function saveStep3(Request $request, FinanceReviewProfile $profile): void
    {
        $profile->fill([
            'cook_percentage'   => (int) $request->input('cook_percentage', 50),
            'food_base_monthly' => $this->parseAmount($request->input('food_base_monthly')),
            'hangout_frequency' => $request->input('hangout_frequency', 'jarang'),
        ]);
    }

    private function saveStep4(Request $request, FinanceReviewProfile $profile): void
    {
        $profile->fill([
            'transport_type'    => $request->input('transport_type'),
            'commute_km_daily'  => (int) $request->input('commute_km_daily', 0),
            'transport_monthly' => $this->parseAmount($request->input('transport_monthly')),
        ]);
    }

    private function saveStep5(Request $request, FinanceReviewProfile $profile): void
    {
        $profile->fill([
            'bills_snapshot'     => json_decode($request->input('bills_json', '[]'), true) ?? [],
            'recurring_snapshot' => json_decode($request->input('recurring_json', '[]'), true) ?? [],
        ]);
    }

    private function saveStep6(Request $request, FinanceReviewProfile $profile): void
    {
        $rows = json_decode($request->input('tanggungan_json', '[]'), true) ?? [];
        $totalRemittance = collect($rows)->sum(fn($r) => (int) ($r['amount'] ?? 0));

        $profile->fill([
            'tanggungan_snapshot' => $rows,
            'tanggungan_count'    => count($rows),
            'family_remittance'   => $totalRemittance,
            'rokok_monthly'       => $this->parseAmount($request->input('rokok_monthly')),
            'gym_monthly'         => $this->parseAmount($request->input('gym_monthly')),
            'asuransi_monthly'    => $this->parseAmount($request->input('asuransi_monthly')),
            'mudik_annual'        => $this->parseAmount($request->input('mudik_annual')),
        ]);
    }

    private function saveStep7(Request $request, FinanceReviewProfile $profile): void
    {
        $profile->fill([
            'debts_snapshot' => json_decode($request->input('debts_json', '[]'), true) ?? [],
        ]);
    }

    private function parseAmount(?string $value): int
    {
        if ($value === null || $value === '') return 0;
        return (int) preg_replace('/[^0-9]/', '', $value);
    }
}
