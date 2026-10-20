<?php

namespace App\Actions;

use App\Cloud\Models\CreditTransaction;
use App\Enums\MessageDirection;
use App\Enums\ReplyClassification;
use App\Models\AgentRun;
use App\Models\CampaignLead;
use App\Models\Company;
use App\Models\Message;
use App\Support\CurrentProject;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

/**
 * The headline numbers of the current project: the Dashboard's stat strip, and
 * one row of the organization overview (run once per project under
 * `CurrentProject::run()`), so both read the same figures the same way.
 *
 * The headline number is the POSITIVE reply rate, not the raw one: a raw rate
 * counts "no thanks" and out-of-office alongside real interest, and a dashboard
 * that flatters is worse than none. Sends and replies are counted from
 * `messages` rather than from campaign state, because a mail that left is a fact
 * and a status is a summary.
 *
 * `$since` narrows every count to what happened from then on (the overview's
 * "this week"), except `awaiting_human`: that is a state right now, not an
 * event, so it has no window.
 */
class SummarizeProjectStats
{
    public function __construct(private CurrentProject $currentProject) {}

    /**
     * @return array{companies_found: int, companies_kept: int, sent: int, replies: int, positive: int, positive_rate: int|null, awaiting_human: int, credits_spent?: int, tokens_in?: int, tokens_out?: int}
     */
    public function handle(?CarbonInterface $since = null): array
    {
        // Never unscoped: with no project set, every count below would be
        // the whole instance's.
        $this->currentProject->getOrFail();

        // `Message` carries no `project_id` of its own, same as `CampaignLead`
        // below: `whereHas('lead')` is what confines it to the current
        // project rather than every message on the instance.
        $sent = Message::query()->whereHas('lead')->where('direction', MessageDirection::Outbound)->whereNotNull('sent_at')->when($since, fn ($query) => $query->where('sent_at', '>=', $since))->count();
        $positive = Message::query()
            ->whereHas('lead')
            ->where('direction', MessageDirection::Inbound)
            ->where('classification', ReplyClassification::Interested)
            ->when($since, fn ($query) => $query->where('received_at', '>=', $since))
            ->count();

        return [
            'companies_found' => Company::query()->when($since, fn ($query) => $query->where('created_at', '>=', $since))->count(),
            'companies_kept' => Company::query()->contactable()->when($since, fn ($query) => $query->where('created_at', '>=', $since))->count(),
            'sent' => $sent,
            'replies' => Message::query()->whereHas('lead')->where('direction', MessageDirection::Inbound)->when($since, fn ($query) => $query->where('received_at', '>=', $since))->count(),
            'positive' => $positive,
            'positive_rate' => $sent === 0 ? null : (int) round($positive / $sent * 100),
            'awaiting_human' => CampaignLead::query()
                ->whereHas('campaign')
                ->where('pause_reason', 'awaiting_human')
                ->count(),
            // Tokens on self-hosted, credits spent on cloud - never both,
            // and never a conversion between them: a self-hosted operator
            // pays their own provider and wants token counts; a cloud
            // customer is billed in credits and must never see a token
            // count or a model name, whatever it would convert to.
            ...(config('eveil.edition') === 'cloud'
                ? [
                    'credits_spent' => abs((int) CreditTransaction::query()
                        ->where('type', 'debit')
                        ->whereHas('agentRun')
                        ->when($since, fn ($query) => $query->where('created_at', '>=', $since))
                        ->sum('credits')),
                ]
                : [
                    'tokens_in' => (int) AgentRun::query()->when($since, fn ($query) => $query->where('created_at', '>=', $since))->sum('tokens_in'),
                    'tokens_out' => (int) AgentRun::query()->when($since, fn ($query) => $query->where('created_at', '>=', $since))->sum('tokens_out'),
                ]),
        ];
    }

    /**
     * Several projects' figures as one: every count added up, and the rate
     * recomputed from the sums rather than averaged, so a project with three
     * sends cannot weigh as much as one with three thousand.
     *
     * @param  Collection<int, array<string, int|null>>  $stats
     * @return array<string, int|null>
     */
    public function sum(Collection $stats): array
    {
        $totals = collect($stats->first() ?? [])
            ->keys()
            ->mapWithKeys(fn (string $key): array => [$key => (int) $stats->sum($key)])
            ->all();
        $totals['positive_rate'] = ($totals['sent'] ?? 0) === 0 ? null : (int) round($totals['positive'] / $totals['sent'] * 100);

        return $totals;
    }
}
