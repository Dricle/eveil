<?php

namespace App\Jobs;

use App\Actions\WriteSequence;
use App\Enums\AgentRunStatus;
use App\Models\AgentRun;
use App\Models\Project;
use App\Models\TargetProfile;
use App\Support\CurrentProject;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

/**
 * Writing three mails on the expensive model takes a minute or two, so the
 * request never waits for it: the campaign appears in the list when it is
 * written, as a draft nobody has sent anything with.
 *
 * The run row is opened by whoever queues this, as `pending`, and carried here
 * so the metering middleware claims it instead of opening a second one.
 */
class WriteCampaign implements ShouldQueue
{
    use Queueable;

    public function __construct(public Project $project, public TargetProfile $targetProfile, public AgentRun $run)
    {
        $this->onQueue('ai');
    }

    public function handle(WriteSequence $write, CurrentProject $currentProject): void
    {
        $currentProject->run(
            $this->project,
            fn () => $write->handle($this->project, $this->targetProfile, $this->run),
        );
    }

    /**
     * What the metering middleware cannot see: the job failing before or after
     * the provider call, which would otherwise leave the row on `pending` for
     * good and the screen spinning.
     */
    public function failed(Throwable $e): void
    {
        if ($this->run->refresh()->status->isInFlight()) {
            $this->run->update([
                'status' => AgentRunStatus::Failed,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
