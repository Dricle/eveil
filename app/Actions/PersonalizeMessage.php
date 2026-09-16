<?php

namespace App\Actions;

use App\Ai\Agents\MessagePersonalizer;
use App\Models\AgentRun;
use App\Models\CampaignStep;
use App\Models\CompanyTargetEvaluation;
use App\Models\EmailExample;
use App\Models\Lead;
use App\Models\StepVariant;
use Illuminate\Support\Collection;
use RuntimeException;

/**
 * One step of a sequence, rewritten for one lead.
 *
 * Nobody researches a prospect by hand here: the opener is built from what the
 * pipeline already observed. The fit reason written when the company was
 * qualified, plus the product portrait: which is the entire reason discovery
 * and personalisation share one knowledge base.
 *
 * The result is deliberately NOT stored. Until sending exists there is nothing
 * to store it for, and a cached mail written days before it goes out is a mail
 * that no longer matches what the user has since edited.
 *
 * @phpstan-type Personalisation array{subject: string, body: string, step_variant_id: int}
 */
class PersonalizeMessage
{
    /**
     * @return Personalisation
     */
    public function handle(CampaignStep $step, Lead $lead, ?AgentRun $run = null): array
    {
        $variant = $this->pickVariant($step);

        if ($variant === null) {
            throw new RuntimeException('This step has no mail to personalise.');
        }

        $agent = new MessagePersonalizer(
            $step->campaign->project,
            $step,
            $lead,
            $variant->subject,
            $variant->body,
            $this->fitReason($step, $lead),
            EmailExample::promptDigest(),
        );

        if ($run !== null) {
            $agent->recordInto($run);
        }

        $response = $agent->personalize();

        return [
            'subject' => (string) ($response->structured['subject'] ?? $variant->subject),
            'body' => (string) ($response->structured['body'] ?? $variant->body),
            // Which template this came from, so the eventual `Message` row
            // can be traced back to it: the one thing that lets a step's
            // own track record ever be measured.
            'step_variant_id' => $variant->id,
        ];
    }

    /**
     * A/B split: each variant's `weight` is its share of sends, so a step with
     * one variant always picks it and a step with two equal weights splits
     * roughly fifty-fifty over enough leads.
     */
    private function pickVariant(CampaignStep $step): ?StepVariant
    {
        /** @var Collection<int, StepVariant> $variants */
        $variants = $step->variants()->get();

        $total = $variants->sum('weight');

        if ($variants->isEmpty() || $total <= 0) {
            return $variants->first();
        }

        $pick = random_int(1, $total);

        foreach ($variants as $variant) {
            $pick -= $variant->weight;

            if ($pick <= 0) {
                return $variant;
            }
        }

        return $variants->last();
    }

    /**
     * The evaluation written for the segment this campaign is aimed at, and
     * failing that the best any profile thought of the company: a lead surfaced
     * by two profiles still deserves the sharper of the two openers.
     */
    private function fitReason(CampaignStep $step, Lead $lead): ?string
    {
        if ($lead->company_id === null) {
            return null;
        }

        return CompanyTargetEvaluation::query()
            ->where('company_id', $lead->company_id)
            ->when(
                $step->campaign->target_profile_id !== null,
                fn ($query) => $query->orderByRaw(
                    'case when target_profile_id = ? then 0 else 1 end',
                    [$step->campaign->target_profile_id],
                ),
            )
            ->orderByDesc('fit_score')
            ->value('fit_reason');
    }
}
