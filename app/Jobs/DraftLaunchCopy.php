<?php

namespace App\Jobs;

use App\Actions\DraftLaunchCopy as DraftLaunchCopyAction;
use App\Enums\AgentRunStatus;
use App\Models\AgentRun;
use App\Models\LaunchItem;
use App\Support\CurrentProject;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

/**
 * One venue's copy, written off the request. The run row is opened as
 * `pending` by whoever queues this and pointed at by the item, which is how
 * the checklist shows a spinner on that one row.
 */
class DraftLaunchCopy implements ShouldQueue
{
    use Queueable;

    public function __construct(public LaunchItem $item, public AgentRun $run)
    {
        $this->onQueue('ai');
    }

    public function handle(DraftLaunchCopyAction $draft, CurrentProject $currentProject): void
    {
        $currentProject->run($this->item->project, fn () => $draft->handle($this->item, $this->run));
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
