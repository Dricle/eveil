<?php

namespace App\Ai;

use App\Ai\Agents\IcpDeriver;
use App\Enums\IcpSource;
use App\Models\Icp;
use App\Models\Project;
use Illuminate\Support\Collection;
use Laravel\Ai\Responses\StructuredAgentResponse;
use RuntimeException;

/**
 * Knowledge base in, target profiles out (ADR-015). This is the step that turns
 * "I know what this product is" into "I know who to contact", and the reason
 * the user never fills in a targeting form.
 */
class DeriveIcps
{
    /**
     * @return Collection<int, Icp>
     */
    public function handle(Project $project, bool $replace = false): Collection
    {
        if ($project->knowledge_base === null) {
            throw new RuntimeException(
                "{$project->name} has no knowledge base yet. Run eveil:analyze first — profiles are "
                .'derived from the product, not guessed from its URL.'
            );
        }

        /** @var StructuredAgentResponse $response */
        $response = (new IcpDeriver($project))->prompt($this->prompt($project));

        /** @var array<int, array<string, mixed>> $profiles */
        $profiles = $response->structured['profiles'] ?? [];

        if ($profiles === []) {
            return new Collection;
        }

        if ($replace) {
            $this->discardPreviousAgentProfiles($project);
        }

        return (new Collection($profiles))
            ->map(fn (array $profile): Icp => $this->store($project, $profile))
            ->values();
    }

    /**
     * Only what the agent produced is thrown away. A profile the user wrote or
     * corrected survives every re-derivation — same rule as the knowledge base.
     */
    private function discardPreviousAgentProfiles(Project $project): void
    {
        Icp::query()
            ->where('project_id', $project->id)
            ->where('source', IcpSource::Agent)
            ->delete();
    }

    /**
     * @param  array<string, mixed>  $profile
     */
    private function store(Project $project, array $profile): Icp
    {
        $name = is_string($profile['name'] ?? null) && $profile['name'] !== ''
            ? $profile['name']
            : 'Profil sans nom';

        return Icp::create([
            'project_id' => $project->id,
            'name' => $name,
            'criteria' => collect($profile)->except('name')->all(),
            'source' => IcpSource::Agent,
            'is_active' => true,
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
