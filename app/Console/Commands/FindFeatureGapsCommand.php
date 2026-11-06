<?php

namespace App\Console\Commands;

use App\Ai\Agents\CompetitorAnalyst;
use App\Enums\AgentRunStatus;
use App\Jobs\FindFeatureGaps;
use App\Models\AgentRun;
use App\Models\Project;
use Illuminate\Console\Command;

/**
 * Queues a feature-gap pass for every project whose knowledge base names
 * competitors. Before this ran on a schedule, the pass only followed a full
 * website analysis, so a project analysed once never got one again.
 */
class FindFeatureGapsCommand extends Command
{
    protected $signature = 'eveil:feature-gaps {--project= : Only this project id}';

    protected $description = 'Compare each project with its competitors and list the features it lacks';

    public function handle(): int
    {
        $projects = Project::query()
            ->when($this->option('project'), fn ($query, $id) => $query->whereKey($id))
            ->whereNotNull('knowledge_base')
            ->get()
            ->filter(fn (Project $project): bool => ! empty($project->knowledge_base['competitors']));

        foreach ($projects as $project) {
            FindFeatureGaps::dispatch($project, AgentRun::create([
                'project_id' => $project->id,
                'agent' => CompetitorAnalyst::slug(),
                'status' => AgentRunStatus::Pending,
            ]));
        }

        $this->info("Queued a feature-gap pass for {$projects->count()} project(s).");

        return self::SUCCESS;
    }
}
