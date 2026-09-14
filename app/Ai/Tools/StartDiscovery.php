<?php

namespace App\Ai\Tools;

use App\Actions\RunDiscovery;
use App\Models\Project;
use App\Models\TargetProfile;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Concerns\InteractsWithApprovals;
use Laravel\Ai\Contracts\Approvable;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Stringable;

/**
 * Starts a real discovery run: a graph of queued jobs that searches for and
 * qualifies candidates. Gated behind approval, unlike the read-only tools
 * above it, because it is a real dispatch and not a lookup.
 */
class StartDiscovery implements Approvable, Tool
{
    use InteractsWithApprovals;

    public function __construct(private Project $project)
    {
        $this->requireApproval('This starts a real discovery run to search for and qualify candidates.');
    }

    public function description(): Stringable|string
    {
        return <<<'TEXT'
        Starts a discovery run for one target profile, searching for and qualifying
        new candidate companies. Optionally steer THIS run toward something specific
        the user asked for - a different angle, a narrower area, a segment worth
        trying - without changing the target profile itself. Also how to spawn a
        fresh search exploring a new angle on the same profile: start another run
        with different guidance rather than waiting for the automatic cadence.
        TEXT;
    }

    public function handle(Request $request): Stringable|string
    {
        $targetProfile = TargetProfile::query()
            ->where('project_id', $this->project->id)
            ->find($request->integer('target_profile_id'));

        if ($targetProfile === null) {
            return 'No target profile with that id exists on this project. Call list_target_profiles first.';
        }

        $guidance = $request->string('guidance')->value();

        $run = app(RunDiscovery::class)->handle($targetProfile, guidance: $guidance === '' ? null : $guidance);

        return "Discovery run #{$run->id} started for \"{$targetProfile->name}\".";
    }

    /**
     * @return array<string, mixed>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'target_profile_id' => $schema->integer()
                ->description('The target profile to search for, from list_target_profiles.')
                ->required(),

            'guidance' => $schema->string()
                ->description('What the user specifically wants THIS run to explore, in their own words. Omit for the profile\'s own default.'),
        ];
    }
}
