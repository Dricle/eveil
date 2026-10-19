<?php

namespace App\Jobs;

use App\Actions\ReviewAds as ReviewAdsAction;
use App\Enums\AgentRunStatus;
use App\Models\AgentRun;
use App\Models\Project;
use App\Support\CurrentProject;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

/**
 * One project's daily ad review. On the `ai` queue, unique per project: two
 * reviews reading the same numbers would propose the same moves twice.
 */
class ReviewAds implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public function __construct(public Project $project, public AgentRun $run)
    {
        $this->onQueue('ai');
    }

    public function uniqueId(): string
    {
        return (string) $this->project->id;
    }

    public function handle(ReviewAdsAction $review, CurrentProject $currentProject): void
    {
        $currentProject->run($this->project, fn () => $review->handle($this->project, $this->run));
    }

    /**
     * Covers what the metering listener cannot see, so the row never stays
     * pending for good.
     */
    public function failed(Throwable $e): void
    {
        if ($this->run->refresh()->status->isInFlight()) {
            $this->run->update(['status' => AgentRunStatus::Failed, 'error' => $e->getMessage()]);
        }
    }
}
