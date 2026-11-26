<?php

namespace App\Jobs;

use App\Actions\PrepareLaunch as PrepareLaunchAction;
use App\Enums\AgentRunStatus;
use App\Models\AgentRun;
use App\Models\Project;
use App\Support\CurrentProject;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

/**
 * Fills the launch checklist off the request: a model call and an upsert of
 * the whole catalog. The run row is opened as `pending` by whoever queues
 * this, so the page can tell "preparing" from "never asked".
 */
class PrepareLaunch implements ShouldQueue
{
    use Queueable;

    public function __construct(public Project $project, public AgentRun $run)
    {
        $this->onQueue('ai');
    }

    public function handle(PrepareLaunchAction $prepare, CurrentProject $currentProject): void
    {
        $currentProject->run($this->project, fn () => $prepare->handle($this->project, $this->run));
    }

    /**
     * Covers what the metering listener cannot see: a failure before or
     * after the model call.
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
