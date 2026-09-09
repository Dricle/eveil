<?php

namespace App\Actions;

use App\Ai\Agents\VariantWriter;
use App\Models\AgentRun;
use App\Models\CampaignStep;
use App\Models\StepVariant;
use Laravel\Ai\Responses\StructuredAgentResponse;
use RuntimeException;

/**
 * A second wording for a step that already has one, written to A/B test
 * against the original rather than typed by hand.
 */
class WriteVariant
{
    public function handle(CampaignStep $step, ?AgentRun $run = null): StepVariant
    {
        // The original, always: a step already running three variants still
        // tests every new one against the same control, so results stay
        // comparable across the whole set.
        $original = $step->variants()->orderBy('id')->first();

        if ($original === null) {
            throw new RuntimeException('This step has no mail to write an alternate for.');
        }

        $agent = new VariantWriter($step->campaign->project);

        if ($run !== null) {
            $agent->recordInto($run);
        }

        /** @var StructuredAgentResponse $response */
        $response = $agent->prompt($this->prompt($step, $original->subject, $original->body));

        return $step->variants()->create([
            'subject' => (string) ($response->structured['subject'] ?? $original->subject),
            'body' => (string) ($response->structured['body'] ?? $original->body),
            'language' => null,
            'weight' => 1,
        ]);
    }

    private function prompt(CampaignStep $step, string $subject, string $body): string
    {
        $project = $step->campaign->project;
        $language = $project->default_language
            ?: 'the language the product knowledge base below is written in';

        $context = [
            'product' => $project->knowledge_base,
            'step_intent' => $step->config['intent'] ?? null,
            'language' => $language,
        ];

        $json = (string) json_encode($context, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        return "Current mail for this step:\nSubject: {$subject}\n\n{$body}\n\n---\n\n{$json}";
    }
}
