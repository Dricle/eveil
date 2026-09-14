<?php

namespace App\Ai\Tools;

use App\Models\Project;
use App\Models\TargetProfile;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Stringable;

/**
 * Read-only: one target profile's full criteria. What ListTargetProfiles only
 * gives the shape of - read this before proposing anything to
 * UpdateTargetProfile, same reasoning as GetCampaign before UpdateSequence.
 */
class GetTargetProfile implements Tool
{
    public function __construct(private Project $project) {}

    public function description(): Stringable|string
    {
        return 'Reads one target profile\'s full detail: every criteria field, not just the shape ListTargetProfiles gives.';
    }

    public function handle(Request $request): Stringable|string
    {
        $profile = TargetProfile::query()
            ->where('project_id', $this->project->id)
            ->find($request->integer('target_profile_id'));

        if ($profile === null) {
            return 'No target profile with that id exists on this project. Call ListTargetProfiles first.';
        }

        return (string) json_encode([
            'id' => $profile->id,
            'name' => $profile->name,
            'type' => $profile->type->value,
            'is_active' => $profile->is_active,
            'source' => $profile->source->value,
            ...$profile->criteria,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'target_profile_id' => $schema->integer()
                ->description('The target profile to read, from ListTargetProfiles.')
                ->required(),
        ];
    }
}
