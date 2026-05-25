<?php

namespace App\Jobs;

use App\Models\Entry;
use App\Services\BehavioralMemoryService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * ProcessBehavioralCorrection — queue: low
 *
 * Runs when a user corrects Butler (wrong account, wrong category, etc.).
 * Weakens the old preference, strengthens the new one,
 * and stamps the entry with the correction_reason.
 */
class ProcessBehavioralCorrection implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $tries = 3;
    public $backoff = 10;

    public function __construct(
        public readonly int    $entryId,
        public readonly string $correctionReason, // wrong_account|wrong_category|etc
        public readonly string $domain,           // behavioral_memory domain
        public readonly string $oldSubject,
        public readonly string $newSubject,
        public readonly array  $newValue
    ) {}

    public function handle(BehavioralMemoryService $memory): void
    {
        $entry = Entry::with('user')->find($this->entryId);
        if (!$entry || !$entry->user) {
            return;
        }

        // Stamp the correction reason on the entry for analytics
        $entry->update(['correction_reason' => $this->correctionReason]);

        // Weaken old / strengthen new
        $memory->correct(
            $entry->user->id,
            $this->domain,
            $this->oldSubject,
            $this->newSubject,
            $this->newValue
        );

        Log::info('Behavioral correction processed', [
            'entry_id'  => $this->entryId,
            'reason'    => $this->correctionReason,
            'domain'    => $this->domain,
            'old'       => $this->oldSubject,
            'new'       => $this->newSubject,
        ]);
    }
}
