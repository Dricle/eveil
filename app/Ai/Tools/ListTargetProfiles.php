<?php

namespace App\Ai\Tools;

use App\Models\Project;
use App\Models\TargetProfile;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Stringable;

/**
 * Read-only: what the project is already targeting, so the model can point a
 * discovery run or a sequence draft at an existing profile instead of asking
 * the user to spell out the target profile id.
 */
class ListTargetProfiles implements Tool
{
    public function __construct(private Project $project) {}

    public function description(): Stringable|string
    {
        return 'Lists the target profiles (customer or partner segments) already defined for this project.';
    }

    public function handle(Request $request): Stringable|string
    {
        $profiles = TargetProfile::query()
            ->where('project_id', $this->project->id)
            ->get(['id', 'name', 'type', 'is_active'])
            ->map(fn (TargetProfile $profile): array => [
                'id' => $profile->id,
                'name' => $profile->name,
                'type' => $profile->type->value,
                'active' => $profile->is_active,
            ]);

        if ($profiles->isEmpty()) {
            return 'This project has no target profiles yet.';
        }

        return (string) json_encode($profiles->all());
    }

    /**
     * @return array<string, mixed>
     */
    public function schema(JsonSchema $schema): array
    {
        return [];
    }
}
