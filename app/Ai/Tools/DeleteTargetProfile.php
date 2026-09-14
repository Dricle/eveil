<?php

namespace App\Ai\Tools;

use App\Models\Project;
use App\Models\TargetProfile;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Concerns\InteractsWithApprovals;
use Laravel\Ai\Contracts\Approvable;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Stringable;

/**
 * Deletes a target profile. Gated behind approval, unlike
 * CreateTargetProfile/UpdateTargetProfile: this cascades to its own discovery
 * run history and cannot be undone, closer to StartDiscovery's "a real
 * dispatch" than to a note or a correction.
 */
class DeleteTargetProfile implements Approvable, Tool
{
    use InteractsWithApprovals;

    public function __construct(private Project $project)
    {
        $this->requireApproval('This permanently deletes a target profile and its discovery run history.');
    }

    public function description(): Stringable|string
    {
        return 'Permanently deletes a target profile, along with its discovery run history. Cannot be undone.';
    }

    public function handle(Request $request): Stringable|string
    {
        $profile = TargetProfile::query()
            ->where('project_id', $this->project->id)
            ->find($request->integer('target_profile_id'));

        if ($profile === null) {
            return 'No target profile with that id exists on this project. Call ListTargetProfiles first.';
        }

        $name = $profile->name;
        $profile->delete();

        return "Target profile \"{$name}\" deleted.";
    }

    /**
     * @return array<string, mixed>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'target_profile_id' => $schema->integer()
                ->description('The target profile to delete, from ListTargetProfiles.')
                ->required(),
        ];
    }
}
