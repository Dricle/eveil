<?php

namespace App\Ai\Tools;

use App\Models\Campaign;
use App\Models\Project;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Stringable;

/**
 * Read-only: the sequences already drafted or running for this project, so
 * the model can find the one a vague request ("update my SaaS sequence")
 * refers to, and confirm what UpdateSequence actually needs - a campaign id.
 */
class ListCampaigns implements Tool
{
    public function __construct(private Project $project) {}

    public function description(): Stringable|string
    {
        return 'Lists this project\'s campaigns (outreach sequences), with their id, name, status and step count.';
    }

    public function handle(Request $request): Stringable|string
    {
        $campaigns = Campaign::query()
            ->where('project_id', $this->project->id)
            ->withCount('steps')
            ->latest('id')
            ->get()
            ->map(fn (Campaign $campaign): array => [
                'id' => $campaign->id,
                'name' => $campaign->name,
                'status' => $campaign->status->value,
                'target_profile_id' => $campaign->target_profile_id,
                'steps' => $campaign->steps_count,
            ]);

        if ($campaigns->isEmpty()) {
            return 'This project has no campaigns yet.';
        }

        return (string) json_encode($campaigns->all());
    }

    /**
     * @return array<string, mixed>
     */
    public function schema(JsonSchema $schema): array
    {
        return [];
    }
}
