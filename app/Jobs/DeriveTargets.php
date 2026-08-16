<?php

namespace App\Jobs;

use App\Actions\DeriveTargetProfiles;
use App\Enums\AgentRunStatus;
use App\Models\AgentRun;
use App\Models\Project;
use App\Support\CurrentProject;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

/**
 * Who to contact, worked out from the product rather than typed into a
 * targeting form. Reading the portrait and calling a model takes a minute or
 * two, so the request never waits for it.
 *
 * The run row is opened by whoever queues this, as `pending`, and carried here
 * so the metering middleware claims it instead of opening a second one. That
 * row is the only record of the work: the job holds no state of its own.
 */
class DeriveTargets implements ShouldQueue
{
    use Queueable;

    public function __construct(public Project $project, public AgentRun $run)
    {
        $this->onQueue('ai');
    }

    public function handle(DeriveTargetProfiles $derive, CurrentProject $currentProject): void
    {
        // Only what the agent wrote last time is replaced. A profile the user
        // wrote or corrected survives every re-derivation.
        $currentProject->run(
            $this->project,
            fn () => $derive->handle($this->project, replace: true, run: $this->run),
        );
    }

    /**
     * A provider that throws is already recorded by the metering middleware.
     * This covers what it cannot see: the job failing before or after the call
     * — a knowledge base that is not there, a worker that gave up retrying —
     * which would otherwise leave the row on `pending` for good.
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
