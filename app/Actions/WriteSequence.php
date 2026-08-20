<?php

namespace App\Actions;

use App\Ai\Agents\SequenceWriter;
use App\Enums\CampaignStatus;
use App\Enums\CampaignStepType;
use App\Models\AgentRun;
use App\Models\Campaign;
use App\Models\CampaignStep;
use App\Models\Project;
use App\Models\TargetProfile;
use Illuminate\Support\Facades\DB;
use Laravel\Ai\Responses\StructuredAgentResponse;
use RuntimeException;

/**
 * Product portrait and target segment in, a draft sequence out. The user never
 * starts from an empty template: they start from something to correct, which
 * is the only version of "generate a campaign" that saves anyone time.
 *
 * The campaign lands as a draft and nothing sends until someone activates it.
 */
class WriteSequence
{
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

        return $this->store($project, $targetProfile, (string) ($response->structured['name'] ?? ''), $steps);
    }

    /**
     * @param  array<int, array<string, mixed>>  $steps
     */
    private function store(Project $project, TargetProfile $targetProfile, string $name, array $steps): Campaign
    {
        return DB::transaction(function () use ($project, $targetProfile, $name, $steps): Campaign {
            $campaign = Campaign::create([
                'project_id' => $project->id,
                'target_profile_id' => $targetProfile->id,
                'name' => $name !== '' ? $name : $targetProfile->name,
                'status' => CampaignStatus::Draft,
            ]);

            foreach (array_values($steps) as $position => $step) {
                $type = CampaignStepType::tryFrom((string) ($step['type'] ?? '')) ?? CampaignStepType::Email;

                /** @var CampaignStep $created */
                $created = $campaign->steps()->create([
                    'position' => $position + 1,
                    'type' => $type,
                    // A wait with no duration would run the sequence straight
                    // through, which reads as automation at the other end.
                    'delay_hours' => $type === CampaignStepType::Wait ? max(1, (int) ($step['delay_hours'] ?? 0)) : null,
                    'config' => ['intent' => (string) ($step['intent'] ?? '')],
                ]);

                if ($type !== CampaignStepType::Email) {
                    continue;
                }

                $created->variants()->create([
                    'subject' => (string) ($step['subject'] ?? ''),
                    'body' => (string) ($step['body'] ?? ''),
                    // Null, not the market's language: the body is rewritten per
                    // company in the company's own language, and a value here
                    // would mark it as a hand-written translation.
                    'language' => null,
                    'weight' => 1,
                ]);
            }

            return $campaign;
        });
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

        return "Product: {$project->name} ({$project->url})\n\n{$portrait}\n\n"
            ."Segment [{$targetProfile->name}], of kind {$targetProfile->type->value}:\n{$criteria}";
    }
}
