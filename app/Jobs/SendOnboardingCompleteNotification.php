<?php

namespace App\Jobs;

use App\Models\User;
use App\Services\TelegramService;
use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * Notifies the user in Telegram once webview onboarding is complete.
 * Per integration guide: sends account summary + usage examples.
 */
class SendOnboardingCompleteNotification implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $tries = 3;
    public $backoff = 30;

    public function __construct(public readonly User $user) {}

    public function handle(TelegramService $telegram): void
    {
        $user    = $this->user->fresh();
        $default = $user->defaultAccount;
        $others  = $user->accounts()->where('is_default_spending', false)->pluck('name');

        $accountLine = $default?->name ?? 'belum diset';
        if ($others->isNotEmpty()) {
            $accountLine .= ' + ' . $others->join(', ');
        }

        $calorieLine = $user->daily_calorie_goal
            ? "Target kalori: {$user->daily_calorie_goal} kcal/hari"
            : null;

        $summaryTimeFormatted = $user->summary_time
            ? Carbon::parse($user->summary_time)->format('H:i')
            : '21:00';

        $lines = array_filter([
            "Siap, {$user->name}! Setup selesai. 🎉",
            "",
            "Akun: {$accountLine}",
            $calorieLine,
            "Ringkasan: setiap hari jam {$summaryTimeFormatted}",
            "",
            "Sekarang coba ketik sesuatu:",
            "• makan ayam geprek 35k",
            "• grab 18rb",
            "• gajian 5jt",
        ]);

        $telegram->sendMessage(
            (string) $user->telegram_chat_id,
            implode("\n", $lines)
        );
    }
}
