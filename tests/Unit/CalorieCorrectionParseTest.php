<?php

namespace Tests\Unit;

use App\Services\MessageRouter;
use PHPUnit\Framework\Attributes\DataProvider;
use ReflectionMethod;
use Tests\TestCase;

/**
 * Inline calorie-edit parsing (v2.6.5).
 *
 * Pure logic — guards that a "500cal"-style reply is recognized as a calorie
 * correction, while money amounts ("30k", "grab 25rb") and unrelated text are
 * NOT — so editing the last meal never hijacks a real new log.
 */
class CalorieCorrectionParseTest extends TestCase
{
    private function parse(string $message): ?array
    {
        $router = app(MessageRouter::class);
        $m = new ReflectionMethod($router, 'parseCalorieValue');
        $m->setAccessible(true);
        return $m->invoke($router, $message);
    }

    #[DataProvider('calorieExpressions')]
    public function test_recognizes_calorie_expressions(string $input, int $value, bool $explicit): void
    {
        $r = $this->parse($input);
        $this->assertNotNull($r, "Expected '{$input}' to be a calorie expression");
        $this->assertSame($value, $r['value']);
        $this->assertSame($explicit, $r['explicit']);
    }

    public static function calorieExpressions(): array
    {
        return [
            'bare number'      => ['500', 500, false],
            'cal suffix'       => ['500cal', 500, true],
            'space cal'        => ['500 cal', 500, true],
            'kalori'           => ['500 kalori', 500, true],
            'kkal'             => ['350kkal', 350, true],
            'kcal'             => ['350 kcal', 350, true],
            'jadi prefix'      => ['jadi 600cal', 600, true],
            'ganti jadi'       => ['ganti jadi 420', 420, false],
            'ubah'             => ['ubah 700 kalori', 700, true],
        ];
    }

    #[DataProvider('notCalorie')]
    public function test_rejects_non_calorie_messages(string $input): void
    {
        $this->assertNull($this->parse($input), "Expected '{$input}' NOT to be a calorie expression");
    }

    public static function notCalorie(): array
    {
        return [
            'rupiah k'      => ['30k'],
            'rupiah rb'     => ['25rb'],
            'expense line'  => ['grab 25rb'],
            'meal name'     => ['ayam goreng'],
            'money jt'      => ['2jt'],
            'mixed text'    => ['500 kalori nasi goreng'],
            'zero'          => ['0'],
            'empty'         => ['   '],
            'saldo cmd'     => ['saldo'],
        ];
    }

    // ── parseEditMoney: explicit money units only ────────────────────────────

    private function money(string $message): ?int
    {
        $router = app(MessageRouter::class);
        $m = new ReflectionMethod($router, 'parseEditMoney');
        $m->setAccessible(true);
        return $m->invoke($router, $message);
    }

    #[DataProvider('moneyExpressions')]
    public function test_parses_explicit_money(string $input, ?int $expected): void
    {
        $this->assertSame($expected, $this->money($input));
    }

    public static function moneyExpressions(): array
    {
        return [
            '600k'        => ['600k', 600000],
            '600 rb'      => ['600 rb', 600000],
            '600ribu'     => ['600ribu', 600000],
            '2jt'         => ['2jt', 2000000],
            '1,5 juta'    => ['1,5 juta', 1500000],
            'jadi 600k'   => ['jadi 600k', 600000],
            'bare number' => ['600', null],   // bare → not money (calorie path)
            'cal'         => ['600cal', null],
            'meal name'   => ['ayam goreng', null],
        ];
    }

    // ── scaleBareToAmount: "600" after a 500k expense → 600.000 ───────────────

    #[DataProvider('scaleCases')]
    public function test_scales_bare_to_amount(int $n, int $original, int $expected): void
    {
        $router = app(MessageRouter::class);
        $m = new ReflectionMethod($router, 'scaleBareToAmount');
        $m->setAccessible(true);
        $this->assertSame($expected, $m->invoke($router, $n, $original));
    }

    public static function scaleCases(): array
    {
        return [
            '600 after 500k'  => [600, 500000, 600000],
            '30 after 25rb'   => [30, 25000, 30000],
            '3 after 2jt'     => [3, 2000000, 3000000],
            'full number'     => [600000, 500000, 600000], // already same ballpark
        ];
    }
}
