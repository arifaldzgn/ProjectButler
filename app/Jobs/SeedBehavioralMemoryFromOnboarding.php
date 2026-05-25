<?php

namespace App\Jobs;

use App\Models\BehavioralMemory;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * Seeds behavioral_memory with baseline category→account preferences
 * derived from what the user set up in the webview onboarding.
 *
 * These start at low confidence (0.10) and strengthen through usage.
 * If the user's default account is an e-wallet, food/transport get a
 * slightly higher starting confidence (0.20) per integration guide.
 */
class SeedBehavioralMemoryFromOnboarding implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $tries = 3;
    public $backoff = 10;

    public function __construct(public readonly User $user) {}

    public function handle(): void
    {
        $user    = $this->user->fresh(['defaultAccount']);
        $default = $user->defaultAccount;

        if (!$default) {
            return;
        }

        $categoryDefaults = [
            'food_drink', 'transport', 'shopping', 'entertainment', 'other',
        ];

        foreach ($categoryDefaults as $category) {
            BehavioralMemory::updateOrCreate(
                [
                    'user_id' => $user->id,
                    'domain'  => BehavioralMemory::DOMAIN_CATEGORY_ACCOUNT,
                    'subject' => $category,
                ],
                [
                    'value' => [
                        'account_id'   => $default->id,
                        'account_name' => $default->name,
                    ],
                    'behavioral_confidence' => 0.10,
                    'observation_count'     => 0,
                    'user_consented'        => false,
                    'auto_apply'            => false,
                    'last_observed_at'      => now(),
                ]
            );
        }

        // If default is e-wallet → bump confidence for common e-wallet categories
        if ($default->type === 'ewallet') {
            BehavioralMemory::where('user_id', $user->id)
                ->where('domain', BehavioralMemory::DOMAIN_CATEGORY_ACCOUNT)
                ->whereIn('subject', ['food_drink', 'transport'])
                ->update(['behavioral_confidence' => 0.20]);
        }
    }
}
