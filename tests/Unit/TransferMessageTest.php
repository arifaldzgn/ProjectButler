<?php

namespace Tests\Unit;

use App\Services\TelegramService;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Direction-aware transfer copy (v2.6.1).
 *
 * Pure formatting contract — no DB, no network. Guards that the three
 * transfer directions render the correct "from / to" framing so an
 * outgoing transfer never reads like an incoming one.
 */
class TransferMessageTest extends TestCase
{
    private TelegramService $telegram;

    protected function setUp(): void
    {
        parent::setUp();
        $this->telegram = new TelegramService();
    }

    public function test_outgoing_confirmation_mentions_source_not_target(): void
    {
        $msg = $this->telegram->formatTransferConfirmation(
            ['amount' => 50_000, 'direction' => 'out', 'source_fund' => 'GoPay', 'target_fund' => null],
            0.95
        );

        $this->assertStringContainsString('keluar', $msg);
        $this->assertStringContainsString('GoPay', $msg);
        $this->assertStringContainsString('50.000', $msg);
        $this->assertStringNotContainsString('Ke:', $msg);
    }

    public function test_incoming_confirmation_mentions_target_not_source(): void
    {
        $msg = $this->telegram->formatTransferConfirmation(
            ['amount' => 100_000, 'direction' => 'in', 'source_fund' => null, 'target_fund' => 'BCA'],
            0.95
        );

        $this->assertStringContainsString('masuk', $msg);
        $this->assertStringContainsString('BCA', $msg);
        $this->assertStringNotContainsString('Dari:', $msg);
    }

    public function test_internal_confirmation_shows_both_legs(): void
    {
        $msg = $this->telegram->formatTransferConfirmation(
            ['amount' => 200_000, 'direction' => 'internal', 'source_fund' => 'GoPay', 'target_fund' => 'Tabungan'],
            0.96
        );

        $this->assertStringContainsString('Dari: GoPay', $msg);
        $this->assertStringContainsString('Ke: Tabungan', $msg);
    }

    public function test_low_confidence_appends_warning(): void
    {
        $msg = $this->telegram->formatTransferConfirmation(
            ['amount' => 10_000, 'direction' => 'out', 'source_fund' => 'GoPay', 'target_fund' => null],
            0.6
        );

        $this->assertStringContainsString('kurang yakin', $msg);
    }

    #[DataProvider('confirmedLineProvider')]
    public function test_confirmed_line_matches_direction(string $direction, array $parsed, array $needles): void
    {
        $line = $this->telegram->formatConfirmedMessage('transfer', $parsed + ['direction' => $direction], 0);

        foreach ($needles as $needle) {
            $this->assertStringContainsString($needle, $line);
        }
    }

    public static function confirmedLineProvider(): array
    {
        return [
            'incoming' => ['in', ['amount' => 100_000, 'target_fund' => 'BCA'], ['Diterima', 'BCA']],
            'outgoing' => ['out', ['amount' => 50_000, 'source_fund' => 'GoPay'], ['Transfer', 'GoPay']],
            'internal' => ['internal', ['amount' => 200_000, 'source_fund' => 'GoPay', 'target_fund' => 'Tabungan'], ['GoPay', 'Tabungan']],
        ];
    }
}
