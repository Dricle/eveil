<?php

namespace App\Jobs;

use App\Actions\RefreshAcquisitionIdeas as RefreshAcquisitionIdeasAction;
use App\Enums\AgentRunStatus;
use App\Models\AgentRun;
use App\Models\Project;
use App\Support\CurrentProject;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

/**
 * A re-read of the site scoped to acquisition ideas, triggered from chat
 * ("is there anything new I'm missing?"). Crawling and calling a model takes
 * a minute or two, so the request never waits for it.
 *
 * The run row is opened by whoever queues this, as `pending`, and carried
 * here so the metering middleware claims it instead of opening a second one -
 * same shape as `App\Jobs\DeriveTargets`.
 */
class RefreshAcquisitionIdeas implements ShouldQueue
{
    use Queueable;

    public function __construct(public Project $project, public AgentRun $run)
    {
        $this->onQueue('ai');
    }

    public function handle(RefreshAcquisitionIdeasAction $refresh, CurrentProject $currentProject): void
    {
        $currentProject->run($this->project, fn () => $refresh->handle($this->project, $this->run));
    }

    /**
     * A provider that throws is already recorded by the metering middleware.
     * This covers what it cannot see: the job failing before or after the
     * call, or a crawl that never reached the agent at all.
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
