<?php

namespace App\Actions;

use App\Ai\Agents\SequenceWriter;
use App\Models\AgentRun;
use App\Models\Campaign;
use App\Models\EmailExample;
use App\Models\Project;
use App\Models\TargetProfile;
use Laravel\Ai\Responses\StructuredAgentResponse;
use RuntimeException;

/**
 * Product portrait and target segment in, a draft sequence out. The user never
 * starts from an empty template: they start from something to correct, which
 * is the only version of "generate a campaign" that saves anyone time.
 *
 * The campaign lands as a draft and nothing sends until someone activates it.
 */
class GenerateSequence
{
    public function __construct(private StoreSequence $store) {}

    public function handle(Project $project, TargetProfile $targetProfile, ?AgentRun $run = null): Campaign
    {
        if ($project->knowledge_base === null) {
            throw new RuntimeException(
                "{$project->name} has no knowledge base yet. Run eveil:analyze first. A sequence is "
                .'written from the product, not from its URL.'
            );
        }

        $agent = new SequenceWriter($project);

        // The caller already opened a run row when it queued this: report into
        // it instead of leaving a `pending` row behind next to a second one.
        if ($run !== null) {
            $agent->recordInto($run);
        }

        /** @var StructuredAgentResponse $response */
        $response = $agent->prompt($this->prompt($project, $targetProfile));

        /** @var array<int, array<string, mixed>> $steps */
        $steps = $response->structured['steps'] ?? [];

        if ($steps === []) {
            throw new RuntimeException('The writer returned no steps.');
        }

        return $this->store->handle($project, $targetProfile, (string) ($response->structured['name'] ?? ''), $steps);
    }

    private function prompt(Project $project, TargetProfile $targetProfile): string
    {
        $portrait = json_encode(
            $project->knowledge_base,
            JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES,
        );

        $criteria = json_encode(
            $targetProfile->criteria,
            JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES,
        );

        $examples = EmailExample::promptDigest();

        return "Product: {$project->name} ({$project->url})\n\n{$portrait}\n\n"
            ."Segment [{$targetProfile->name}], of kind {$targetProfile->type->value}:\n{$criteria}"
            .($examples === '' ? '' : "\n\n---\n\n{$examples}");
    }
}
