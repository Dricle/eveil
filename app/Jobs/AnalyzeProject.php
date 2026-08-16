<?php

namespace App\Jobs;

use App\Actions\AnalyzeWebsite;
use App\Models\Project;
use App\Support\CurrentProject;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Saving a project starts its analysis: the user gives a URL and the knowledge
 * base builds itself. Crawling a site and calling a model takes minutes, so the
 * request never waits for it.
 */
class AnalyzeProject implements ShouldQueue
{
    use Queueable;

    public function __construct(public Project $project)
    {
        $this->onQueue('ai');
    }

    public function handle(AnalyzeWebsite $analyze, CurrentProject $currentProject): void
    {
        $currentProject->run($this->project, fn () => $analyze->handle($this->project));
    }
}
