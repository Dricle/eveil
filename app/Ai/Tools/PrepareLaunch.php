<?php

namespace App\Ai\Tools;

use App\Ai\Agents\LaunchPlanner;
use App\Enums\AgentRunStatus;
use App\Jobs\PrepareLaunch as PrepareLaunchJob;
use App\Models\AgentRun;
use App\Models\Project;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Stringable;

/**
 * The Launch page's "Prepare my launch": fills the checklist with the
 * venues that suit the product. Needs no approval, same as the button: one
 * cheap model call, and it only ever adds rows.
 */
class PrepareLaunch implements Tool
{
    public function __construct(private Project $project) {}

    public function description(): Stringable|string
    {
        return 'Fills the launch checklist with the places worth posting the product, picked out of hundreds of launch platforms, communities, review sites and directories, plus the subreddits its target profiles found. Runs in the background for a few seconds. Safe to run again: it only adds venues and never touches what was already done.';
    }

    public function handle(Request $request): Stringable|string
    {
        PrepareLaunchJob::dispatch($this->project, AgentRun::create([
            'project_id' => $this->project->id,
            'agent' => LaunchPlanner::slug(),
            'status' => AgentRunStatus::Pending,
        ]));

        return 'Preparing the launch checklist. Call ListLaunchVenues in a moment to see it.';
    }

    /**
     * @return array<string, mixed>
     */
    public function schema(JsonSchema $schema): array
    {
        return [];
    }
}
