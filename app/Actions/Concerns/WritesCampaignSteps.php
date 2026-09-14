<?php

namespace App\Actions\Concerns;

use App\Enums\CampaignStepType;
use App\Models\Campaign;
use App\Models\CampaignStep;

/**
 * Turns a plain steps array (the shape both `SequenceWriter` and Evie
 * produce) into real `CampaignStep`/variant rows. Shared by `StoreSequence`
 * (a fresh campaign) and `UpdateSequence` (an existing one rewritten), which
 * differ only in what happens to the campaign itself, never in this.
 */
trait WritesCampaignSteps
{
    /**
     * @param  array<int, array<string, mixed>>  $steps
     */
    private function writeSteps(Campaign $campaign, array $steps): void
    {
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
    }
}
