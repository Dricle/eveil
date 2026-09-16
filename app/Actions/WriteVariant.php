<?php

namespace App\Actions;

use App\Ai\Agents\VariantWriter;
use App\Models\AgentRun;
use App\Models\CampaignStep;
use App\Models\StepVariant;
use RuntimeException;

/**
 * A second wording for a step that already has one, written to A/B test
 * against every version already running rather than typed by hand. Given a
 * target variant, rewrites it in place instead, per the guidance, rather
 * than adding another version.
 */
class WriteVariant
{
    public function handle(CampaignStep $step, ?string $guidance = null, ?AgentRun $run = null, ?StepVariant $target = null): StepVariant
    {
        // Every version already running, not just the first: a step already
        // on its third variant still needs the fourth to differ from all
        // three, or two of them end up testing the same mail twice.
        $existing = $step->variants()->orderBy('id')->get();

        if ($target === null && $existing->isEmpty()) {
            throw new RuntimeException('This step has no mail to write an alternate for.');
        }

        // Regenerating one variant compares it against its siblings, not
        // against itself: fed its own wording as "already running", the agent
        // would be told to differ from the very mail it is asked to improve.
        $others = $target === null ? $existing : $existing->reject(fn (StepVariant $variant) => $variant->is($target));

        $agent = new VariantWriter($step->campaign->project, $step, $others, $guidance, $target);

        if ($run !== null) {
            $agent->recordInto($run);
        }

        $response = $agent->write();

        $fallback = $target ?? $existing->first();

        $attributes = [
            'subject' => (string) ($response->structured['subject'] ?? $fallback->subject),
            'body' => (string) ($response->structured['body'] ?? $fallback->body),
        ];

        if ($target !== null) {
            $target->update($attributes);

            return $target;
        }

        return $step->variants()->create($attributes + ['language' => null, 'weight' => 1]);
    }
}
