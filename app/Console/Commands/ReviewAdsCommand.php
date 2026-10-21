<?php

namespace App\Console\Commands;

use App\Ai\Agents\AdStrategist;
use App\Enums\AgentRunStatus;
use App\Jobs\ReviewAds;
use App\Models\AgentRun;
use App\Models\Project;
use Illuminate\Console\Command;

/**
 * Queues a review for every project with an ads budget and an account to
 * spend it on. A project with neither costs no model call.
 */
class ReviewAdsCommand extends Command
{
    protected $signature = 'eveil:ads-review {--project= : Only this project id}';

    protected $description = 'Let the ad agent review each project\'s campaigns and act on them';

    public function handle(): int
    {
        $projects = Project::query()
            ->when($this->option('project'), fn ($query, $id) => $query->whereKey($id))
            ->where('ads_monthly_budget_micros', '>', 0)
            ->get()
            ->filter(fn (Project $project): bool => $project->runsAds());

        foreach ($projects as $project) {
            ReviewAds::dispatch($project, AgentRun::create([
                'project_id' => $project->id,
                'agent' => AdStrategist::slug(),
                'status' => AgentRunStatus::Pending,
            ]));
        }

        $this->info("Queued an ad review for {$projects->count()} project(s).");

        return self::SUCCESS;
    }
}
