<?php

namespace App\Actions;

use App\Enums\CampaignStepType;
use App\Enums\EmailStatus;
use App\Models\Campaign;
use App\Models\CampaignStep;
use App\Models\Lead;
use Illuminate\Support\Collection;

/**
 * What one step of a sequence actually looks like once it has been written for
 * a real lead — read before anything is activated, which is the only moment
 * the user can still change their mind for free.
 *
 * A sample rather than the whole list: three mails is enough to see whether the
 * openers are specific, and personalising the list would cost one model call
 * per lead for a page nobody reads to the end.
 */
class PreviewSequence
{
    /**
     * How many leads a preview covers. Three shows whether the opener varies;
     * ten only shows it more slowly and at three times the cost.
     */
    private const SAMPLE = 3;

    public function __construct(private PersonalizeMessage $personalize) {}

    /**
     * @return array{step_id: int|null, messages: array<int, array{lead: string, company: string|null, subject: string, body: string}>}
     */
    public function handle(Campaign $campaign, ?int $stepId = null): array
    {
        $step = $this->step($campaign, $stepId);

        if ($step === null) {
            return ['step_id' => null, 'messages' => []];
        }

        $messages = $this->leads($campaign)
            ->map(function (Lead $lead) use ($step): array {
                $written = $this->personalize->handle($step, $lead);

                return [
                    'lead' => $lead->email ?? trim($lead->first_name.' '.$lead->last_name),
                    'company' => $lead->company?->name,
                    'subject' => $written['subject'],
                    'body' => $written['body'],
                ];
            })
            ->values()
            ->all();

        return ['step_id' => $step->id, 'messages' => $messages];
    }

    /**
     * The step asked for, or the first mail in the sequence — a preview of a
     * wait step is an empty screen.
     */
    private function step(Campaign $campaign, ?int $stepId): ?CampaignStep
    {
        return $campaign->steps
            ->where('type', CampaignStepType::Email)
            ->when($stepId !== null && $stepId > 0, fn (Collection $steps) => $steps->where('id', $stepId))
            ->first();
    }

    /**
     * Real leads, never invented ones: a preview written against a made-up
     * company proves nothing about the mails that will actually go out.
     *
     * `contactable()` is what keeps an existing client out of the sample — and
     * out of the sending, since this is the query that says who a step is
     * written for. Invalid addresses are left out because they are never sent
     * to; a null status means imported and unverified, which is not the same
     * as bad.
     *
     * @return Collection<int, Lead>
     */
    private function leads(Campaign $campaign): Collection
    {
        return Lead::query()
            ->with('company')
            ->where('project_id', $campaign->project_id)
            ->whereNotNull('email')
            ->contactable()
            ->where(fn ($query) => $query->where('email_status', '!=', EmailStatus::Invalid)->orWhereNull('email_status'))
            ->latest('id')
            ->limit(self::SAMPLE)
            ->get();
    }
}
