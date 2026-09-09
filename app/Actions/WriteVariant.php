<?php

namespace App\Actions;

use App\Ai\Agents\VariantWriter;
use App\Models\AgentRun;
use App\Models\CampaignStep;
use App\Models\StepVariant;
use Illuminate\Support\Collection;
use Laravel\Ai\Responses\StructuredAgentResponse;
use RuntimeException;

/**
 * A second wording for a step that already has one, written to A/B test
 * against every version already running rather than typed by hand.
 */
class WriteVariant
{
    public function handle(CampaignStep $step, ?string $guidance = null, ?AgentRun $run = null): StepVariant
    {
        // Every version already running, not just the first: a step already
        // on its third variant still needs the fourth to differ from all
        // three, or two of them end up testing the same mail twice.
        $existing = $step->variants()->orderBy('id')->get();

        if ($existing->isEmpty()) {
            throw new RuntimeException('This step has no mail to write an alternate for.');
        }

        $agent = new VariantWriter($step->campaign->project);

        if ($run !== null) {
            $agent->recordInto($run);
        }

        /** @var StructuredAgentResponse $response */
        $response = $agent->prompt($this->prompt($step, $existing, $guidance));

        $fallback = $existing->first();

        return $step->variants()->create([
            'subject' => (string) ($response->structured['subject'] ?? $fallback->subject),
            'body' => (string) ($response->structured['body'] ?? $fallback->body),
            'language' => null,
            'weight' => 1,
        ]);
    }

    /**
     * @param  Collection<int, StepVariant>  $existing
     */
    private function prompt(CampaignStep $step, Collection $existing, ?string $guidance): string
    {
        $project = $step->campaign->project;
        $language = $project->default_language
            ?: 'the language the product knowledge base below is written in';

        $versions = $existing->values()
            ->map(fn (StepVariant $variant, int $index): string => sprintf(
                "Version %d:\nSubject: %s\n\n%s",
                $index + 1,
                $variant->subject,
                $variant->body,
            ))
            ->implode("\n\n---\n\n");

        $context = [
            'product' => $project->knowledge_base,
            'step_intent' => $step->config['intent'] ?? null,
            'language' => $language,
            // What the user actually wants to learn from this test, in their
            // own words. Null when they left it blank, which is exactly what
            // lets the instructions say "pick your own angle" only then.
            'what_this_version_should_test' => $guidance,
        ];

        $json = (string) json_encode($context, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        return "Version(s) already running for this step:\n\n{$versions}\n\n---\n\n{$json}";
    }
}
