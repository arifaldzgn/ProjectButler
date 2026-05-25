<?php

namespace App\Jobs;

use App\Models\BehavioralMemory;
use App\Models\Entry;
use App\Models\User;
use App\Services\BehavioralMemoryService;
use App\Services\TelegramService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * UpdateBehavioralMemory — queue: low
 *
 * Runs after every CONFIRMED entry.
 * Observes merchant→account and food→calorie patterns.
 * If a pattern crosses the consent threshold (0.80), sends the consent gate.
 */
class UpdateBehavioralMemory implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $tries = 3;
    public $backoff = 10;

    public function __construct(
        public readonly int $entryId,
        public readonly string $chatId
    ) {}

    public function handle(BehavioralMemoryService $memory, TelegramService $telegram): void
    {
        $entry = Entry::with('user')->find($this->entryId);
        if (!$entry || !$entry->user) {
            return;
        }

        $user = $entry->user;
        $consentCandidates = [];

        // ── 1. Learn merchant → account pattern ───────────────────────
        if (
            in_array($entry->type, ['expense', 'bill_payment'])
            && $entry->merchant
            && $entry->source_fund_id
        ) {
            $fund = $entry->sourceFund;
            if ($fund) {
                $row = $memory->observe(
                    $user->id,
                    BehavioralMemory::DOMAIN_MERCHANT_ACCOUNT,
                    $entry->merchant,
                    ['account_id' => $fund->id, 'account_name' => $fund->name]
                );

                if ($row->shouldAskConsent()) {
                    $consentCandidates[] = $row;
                }
            }
        }

        // ── 2. Learn food → calories correction ───────────────────────
        if ($entry->type === 'meal' && $entry->food_item && $entry->calories) {
            $memory->observe(
                $user->id,
                BehavioralMemory::DOMAIN_FOOD_CALORIES,
                $entry->food_item,
                ['kcal' => $entry->calories, 'ai_estimate' => $entry->calories]
            );
        }

        // ── 3. Fire consent gate for qualifying rows ───────────────────
        foreach ($consentCandidates as $row) {
            $memory->markConsentAsked($user->id, $row->domain, $row->subject);

            $accountName = $row->value['account_name'] ?? 'akun ini';
            $subject = $row->subject;

            $telegram->sendConsentGate(
                $this->chatId,
                $subject,
                $accountName,
                $row->domain,
                $row->subject
            );
        }
    }
}
