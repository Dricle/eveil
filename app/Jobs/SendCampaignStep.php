<?php

namespace App\Jobs;

use App\Actions\SendNextStep;
use App\Models\CampaignLead;
use App\Support\CurrentProject;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\Middleware\WithoutOverlapping;

/**
 * One mail leaving. On the `sending` queue, which runs a single process on
 * purpose: bursty cold outreach is what gets a mailbox blocked, and the whole
 * pacing design is worth nothing if ten workers drain the batch at once.
 *
 * Locked per MAILBOX rather than per lead. Two mails from one address in the
 * same second is the pattern being avoided, and the lock is also what keeps two
 * workers from both reading the same remaining allowance and both deciding
 * there is room.
 */
class SendCampaignStep implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public function __construct(public CampaignLead $campaignLead)
    {
        $this->onQueue('sending');
    }

    /**
     * Two jobs for the same lead in the queue at once would send the same step
     * twice — the second one having read the row before the first advanced it.
     */
    public function uniqueId(): string
    {
        return (string) $this->campaignLead->id;
    }

    /**
     * @return array<int, object>
     */
    public function middleware(): array
    {
        return [(new WithoutOverlapping('mailbox:'.$this->campaignLead->email_account_id))->releaseAfter(30)];
    }

    public function handle(SendNextStep $send, CurrentProject $currentProject): void
    {
        $currentProject->run(
            $this->campaignLead->campaign->project,
            fn () => $send->handle($this->campaignLead),
        );
    }
}
