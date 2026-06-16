<?php

namespace Tests\Unit;

use App\Models\User;
use App\Services\EntryService;
use App\Services\TelegramService;
use Carbon\Carbon;
use ReflectionMethod;
use Tests\TestCase;

/**
 * NL analytical queries (v2.6.4).
 *
 * Pure logic — no DB, no network. Guards the period-range boundaries and the
 * response-formatting contract that drive "total gojek bulan ini" style answers.
 */
class AnalyticsQueryTest extends TestCase
{
    private function periodRange(string $period): array
    {
        $svc  = app(EntryService::class);
        $m    = new ReflectionMethod($svc, 'periodRange');
        $m->setAccessible(true);
        $user = new User(['timezone' => 'Asia/Jakarta']);
        return $m->invoke($svc, $user, $period);
    }

    private function format(array $r): string
    {
        return app(TelegramService::class)->formatAnalyticsResponse($r);
    }

    public function test_period_today_is_one_day(): void
    {
        [$start, $end] = $this->periodRange('today');
        $this->assertSame(0, (int) $start->diffInDays($end));
        $this->assertSame('00:00:00', $start->format('H:i:s'));
    }

    public function test_period_week_spans_seven_days(): void
    {
        [$start, $end] = $this->periodRange('week');
        // last 7 days inclusive → 6 whole days between the boundaries
        $this->assertSame(6, (int) $start->diffInDays($end));
    }

    public function test_period_last_month_is_previous_calendar_month(): void
    {
        Carbon::setTestNow(Carbon::create(2026, 6, 14, 12, 0, 0, 'Asia/Jakarta'));
        [$start, $end] = $this->periodRange('last_month');
        $this->assertSame('2026-05-01', $start->format('Y-m-d'));
        $this->assertSame('2026-05-31', $end->format('Y-m-d'));
        Carbon::setTestNow();
    }

    public function test_format_sum_mentions_merchant_amount_and_count(): void
    {
        $msg = $this->format([
            'metric' => 'sum', 'entry_type' => 'expense', 'is_calorie' => false,
            'value' => 420000, 'count' => 8, 'category' => null, 'merchant' => 'gojek',
            'period' => 'month', 'period_label' => 'bulan ini',
        ]);
        $this->assertStringContainsString('gojek', $msg);
        $this->assertStringContainsString('Rp 420.000', $msg);
        $this->assertStringContainsString('8 transaksi', $msg);
        $this->assertStringContainsString('bulan ini', $msg);
    }

    public function test_format_count_metric_uses_times(): void
    {
        $msg = $this->format([
            'metric' => 'count', 'entry_type' => 'expense', 'is_calorie' => false,
            'value' => 5, 'count' => 5, 'category' => null, 'merchant' => 'grab',
            'period' => 'week', 'period_label' => 'minggu ini',
        ]);
        $this->assertStringContainsString('5x', $msg);
        $this->assertStringContainsString('grab', $msg);
    }

    public function test_format_empty_result(): void
    {
        $msg = $this->format([
            'metric' => 'sum', 'entry_type' => 'expense', 'is_calorie' => false,
            'value' => 0, 'count' => 0, 'category' => null, 'merchant' => 'gojek',
            'period' => 'today', 'period_label' => 'hari ini',
        ]);
        $this->assertStringContainsString('Nggak ada', $msg);
        $this->assertStringContainsString('gojek', $msg);
    }

    public function test_format_calorie_sum(): void
    {
        $msg = $this->format([
            'metric' => 'sum', 'entry_type' => 'meal', 'is_calorie' => true,
            'value' => 12500, 'count' => 21, 'category' => null, 'merchant' => null,
            'period' => 'week', 'period_label' => 'minggu ini',
        ]);
        $this->assertStringContainsString('12.500 kcal', $msg);
        $this->assertStringContainsString('21 makan', $msg);
    }
}
