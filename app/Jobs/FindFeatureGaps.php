<?php

namespace App\Jobs;

use App\Actions\FindFeatureGaps as FindFeatureGapsAction;
use App\Enums\AgentRunStatus;
use App\Models\AgentRun;
use App\Models\Project;
use App\Support\CurrentProject;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

/**
 * Competitor sites read for missing features, queued after a website
 * analysis names competitors or from chat. Same shape as
 * `RefreshAcquisitionIdeas`: the run row is opened as `pending` by whoever
 * queues this.
 */
class FindFeatureGaps implements ShouldQueue
{
    use Queueable;

    public function __construct(public Project $project, public AgentRun $run)
    {
        $this->onQueue('ai');
    }

    public function handle(FindFeatureGapsAction $findFeatureGaps, CurrentProject $currentProject): void
    {
        $currentProject->run($this->project, fn () => $findFeatureGaps->handle($this->project, $this->run));
    }

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
