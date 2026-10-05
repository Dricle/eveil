<?php

namespace App\Jobs;

use App\Actions\AnalyzeWebsite;
use App\Ai\Agents\CompetitorAnalyst;
use App\Enums\AgentRunStatus;
use App\Models\AgentRun;
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

        // Competitors are only known once the site has been read, so the
        // feature-gap pass waits for this one rather than running beside it.
        if (! empty($this->project->refresh()->knowledge_base['competitors'])) {
            FindFeatureGaps::dispatch($this->project, AgentRun::create([
                'project_id' => $this->project->id,
                'agent' => CompetitorAnalyst::slug(),
                'status' => AgentRunStatus::Pending,
            ]));
        }
    }
}
