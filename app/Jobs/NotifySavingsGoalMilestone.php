<?php

namespace App\Jobs;

use App\Models\Fund;
use App\Models\User;
use App\Services\TelegramService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;

/**
 * Sends a Telegram notification when a savings goal hits a milestone
 * (25%, 50%, 75%, or 100% of target amount).
 *
 * Uses Cache to prevent re-sending the same milestone notification.
 */
class NotifySavingsGoalMilestone implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $tries   = 3;
    public $backoff = 30;

    public function __construct(
        public readonly User $user,
        public readonly Fund $fund,
        public readonly int  $milestone  // 25, 50, 75, or 100
    ) {}

    public function handle(TelegramService $telegram): void
    {
        // Double-check: did we already send this milestone for this fund?
        $cacheKey = "goal_milestone:{$this->fund->id}:{$this->milestone}";
        if (Cache::has($cacheKey)) {
            return;
        }

        $fund    = $this->fund->fresh();
        $user    = $this->user->fresh();

        if (!$fund || !$user || !$user->telegram_chat_id) {
            return;
        }

        $currentF = 'Rp ' . number_format($fund->current_balance, 0, ',', '.');
        $targetF  = 'Rp ' . number_format($fund->target_amount, 0, ',', '.');
        $pct      = $this->milestone;

        $emoji = match (true) {
            $pct >= 100 => '🎉',
            $pct >= 75  => '🚀',
            $pct >= 50  => '💪',
            default     => '📈',
        };

        if ($pct >= 100) {
            $msg = "{$emoji} *Goal tercapai!*\n\nTabungan _{$fund->name}_ sudah penuh!\n{$currentF} / {$targetF} ✅\n\nKerja keras kamu terbayar 🙌";
        } else {
            $msg = "{$emoji} Tabungan _{$fund->name}_ sudah *{$pct}%*!\n{$currentF} dari {$targetF}";
        }

        $delivered = $telegram->sendMessage((string) $user->telegram_chat_id, $msg);

        if ($delivered) {
            // Cache indefinitely (until fund is deleted/reset) to prevent re-sending
            Cache::put($cacheKey, true, now()->addYears(2));
        }
    }
}
