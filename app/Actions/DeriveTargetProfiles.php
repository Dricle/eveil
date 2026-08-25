<?php

namespace App\Actions;

use App\Ai\Agents\TargetProfileDeriver;
use App\Enums\TargetProfileSource;
use App\Enums\TargetProfileType;
use App\Models\AgentRun;
use App\Models\Project;
use App\Models\TargetProfile;
use App\Support\Settings;
use Illuminate\Support\Collection;
use Laravel\Ai\Responses\StructuredAgentResponse;
use RuntimeException;

/**
 * Knowledge base in, target profiles out. This is the step that turns
 * "I know what this product is" into "I know who to contact", and the reason
 * the user never fills in a targeting form.
 */
class DeriveTargetProfiles
{
    public function __construct(private Settings $settings) {}

    /**
     * @return Collection<int, TargetProfile>
     */
    public function handle(Project $project, bool $replace = false, ?AgentRun $run = null): Collection
    {
        if ($project->knowledge_base === null) {
            throw new RuntimeException(
                "{$project->name} has no knowledge base yet. Run eveil:analyze first. Profiles are "
                .'derived from the product, not guessed from its URL.'
            );
        }

        $agent = new TargetProfileDeriver($project);

        // The caller already opened a run row when it queued this: report into
        // it instead of leaving a `pending` row behind next to a second one.
        if ($run !== null) {
            $agent->recordInto($run);
        }

        /** @var StructuredAgentResponse $response */
        $response = $agent->prompt($this->prompt($project));

        /** @var array<int, array<string, mixed>> $profiles */
        $profiles = $response->structured['profiles'] ?? [];

        if ($profiles === []) {
            return new Collection;
        }

        if ($replace) {
            $this->discardPreviousAgentProfiles($project);
        }

        return (new Collection($profiles))
            ->map(fn (array $profile): TargetProfile => $this->store($project, $profile))
            ->values();
    }

    /**
     * Only what the agent produced is thrown away. A profile the user wrote or
     * corrected survives every re-derivation: same rule as the knowledge base.
     */
    private function discardPreviousAgentProfiles(Project $project): void
    {
        TargetProfile::query()
            ->where('project_id', $project->id)
            ->where('source', TargetProfileSource::Agent)
            ->delete();
    }

    /**
     * @param  array<string, mixed>  $profile
     */
    private function store(Project $project, array $profile): TargetProfile
    {
        $name = is_string($profile['name'] ?? null) && $profile['name'] !== ''
            ? $profile['name']
            : 'Profil sans nom';

        $confidence = $profile['confidence'] ?? null;
        $minConfidence = $this->settings->array('discovery')['min_profile_confidence'];

        return TargetProfile::create([
            'project_id' => $project->id,
            'name' => $name,
            'type' => TargetProfileType::tryFrom((string) ($profile['type'] ?? '')) ?? TargetProfileType::Customer,

            // The angles stay in `criteria` with everything else the search
            // reads: they describe the segment, and only the kind of profile it
            // is has to be queryable on the row itself.
            'criteria' => collect($profile)->except(['name', 'type'])->all(),
            'source' => TargetProfileSource::Agent,

            // Below the floor, the model itself was not confident about this
            // guess: it lands visibly off rather than silently spending budget
            // on the next scheduler tick. `ContinueDiscovery` enforces the same
            // rule independently, but a profile a human never has to notice is
            // worth more than one they have to catch mid-run.
            'is_active' => $confidence === null || $confidence >= $minConfidence,
        ]);
    }

    private function prompt(Project $project): string
    {
        $portrait = json_encode(
            $project->knowledge_base,
            JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES,
        );

        return "Product: {$project->name} ({$project->url})\n\n{$portrait}";
    }
}
