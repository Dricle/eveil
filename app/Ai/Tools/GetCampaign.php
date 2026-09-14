<?php

namespace App\Ai\Tools;

use App\Enums\CampaignStepType;
use App\Models\Campaign;
use App\Models\CampaignStep;
use App\Models\Project;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Stringable;

/**
 * Read-only: a single campaign's full content, so Evie can actually read
 * what a sequence says - subject lines, bodies, timing - rather than only
 * knowing it exists (that's ListCampaigns). What UpdateSequence needs on the
 * way in, this is the way out.
 */
class GetCampaign implements Tool
{
    public function __construct(private Project $project) {}

    public function description(): Stringable|string
    {
        return 'Reads one campaign\'s full content: every step, its subject line and body (for an email step) or duration (for a wait step), so you can review or discuss what it actually says.';
    }

    public function handle(Request $request): Stringable|string
    {
        $campaign = Campaign::query()
            ->where('project_id', $this->project->id)
            ->with('steps.variants')
            ->find($request->integer('campaign_id'));

        if ($campaign === null) {
            return 'No campaign with that id exists on this project. Call ListCampaigns first.';
        }

        return (string) json_encode([
            'id' => $campaign->id,
            'name' => $campaign->name,
            'status' => $campaign->status->value,
            'target_profile_id' => $campaign->target_profile_id,
            'steps' => $campaign->steps->map(fn (CampaignStep $step): array => [
                'position' => $step->position,
                'type' => $step->type->value,
                'delay_hours' => $step->delay_hours,
                'intent' => $step->config['intent'] ?? '',
                ...$step->type === CampaignStepType::Email ? [
                    'subject' => $step->variants->first()?->subject ?? '',
                    'body' => $step->variants->first()?->body ?? '',
                ] : [],
            ])->all(),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'campaign_id' => $schema->integer()
                ->description('The campaign to read, from ListCampaigns.')
                ->required(),
        ];
    }
}
