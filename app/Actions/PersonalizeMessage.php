<?php

namespace App\Actions;

use App\Ai\Agents\MessagePersonalizer;
use App\Models\AgentRun;
use App\Models\CampaignStep;
use App\Models\CompanyTargetEvaluation;
use App\Models\Lead;
use Laravel\Ai\Responses\StructuredAgentResponse;
use RuntimeException;

/**
 * One step of a sequence, rewritten for one lead.
 *
 * Nobody researches a prospect by hand here: the opener is built from what the
 * pipeline already observed — the fit reason written when the company was
 * qualified, plus the product portrait — which is the entire reason discovery
 * and personalisation share one knowledge base.
 *
 * The result is deliberately NOT stored. Until sending exists there is nothing
 * to store it for, and a cached mail written days before it goes out is a mail
 * that no longer matches what the user has since edited.
 *
 * @phpstan-type Personalisation array{subject: string, body: string}
 */
class PersonalizeMessage
{
    /**
     * @return Personalisation
     */
    public function handle(CampaignStep $step, Lead $lead, ?AgentRun $run = null): array
    {
        $variant = $step->variants()->orderBy('id')->first();

        if ($variant === null) {
            throw new RuntimeException('This step has no mail to personalise.');
        }

        $agent = new MessagePersonalizer($step->campaign->project);

        if ($run !== null) {
            $agent->recordInto($run);
        }

        /** @var StructuredAgentResponse $response */
        $response = $agent->prompt($this->prompt($step, $lead, $variant->subject, $variant->body));

        return [
            'subject' => (string) ($response->structured['subject'] ?? $variant->subject),
            'body' => (string) ($response->structured['body'] ?? $variant->body),
        ];
    }

    private function prompt(CampaignStep $step, Lead $lead, string $subject, string $body): string
    {
        $company = $lead->company;
        $campaign = $step->campaign;

        // Read before the array below: the mail is written in the company's
        // language, and English to a small local business kills the reply rate.
        $language = $company !== null && $company->language !== null ? $company->language : $lead->language;

        $context = [
            'product' => $campaign->project->knowledge_base,
            'recipient' => array_filter([
                'first_name' => $lead->first_name,
                'title' => $lead->title,
            ]),
            'company' => $company === null ? null : array_filter([
                'name' => $company->name,
                'industry' => $company->industry,
                'size' => $company->size,
                'location' => $company->location,
                'website' => $company->website,
                'facts' => $company->facts,
            ]),
            // What convinced the qualifier this company was worth writing to,
            // in its own words. This is the opener's raw material.
            'why_this_company' => $this->fitReason($step, $lead),
            'language' => $language ?? 'the language of the company',
            'step_intent' => $step->config['intent'] ?? null,
        ];

        $json = (string) json_encode($context, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        return "Step to rewrite:\nSubject: {$subject}\n\n{$body}\n\n---\n\nWho it is going to:\n{$json}";
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
