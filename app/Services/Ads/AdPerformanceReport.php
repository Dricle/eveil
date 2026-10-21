<?php

namespace App\Services\Ads;

use App\Enums\AdStatus;
use App\Models\Ad;
use App\Models\AdAccount;
use App\Models\AdCampaign;
use App\Models\AdCreative;
use App\Models\AdDecision;
use App\Models\AdMetric;
use App\Models\Project;
use App\Support\Settings;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Collection;

/**
 * Everything the ad agent sees at a review, as plain data: the project's
 * budget and pacing, what each account spends of it, every campaign and ad
 * with lifetime and last-7-days totals, a daily series, confidence intervals and a verdict, each creative's record
 * across networks, and the last decisions with what happened after them.
 *
 * Generous on purpose: a capable model decides better with precise numbers
 * than with a summary. All arithmetic happens here, the agent only judges.
 * Adding a metric is one line in `totals()` and the agent sees it.
 *
 * Money is in the budget's currency units, never micros: that is how a
 * person and a model both read it. Only accounts billed in that currency are
 * usable, so every amount here is in it.
 */
class AdPerformanceReport
{
    private const SERIES_DAYS = 14;

    public function __construct(private AdClassifier $classifier, private Settings $settings) {}

    /**
     * @return array<string, mixed>
     */
    public function build(Project $project): array
    {
        $accounts = $project->adAccounts()->get();

        $campaigns = AdCampaign::query()
            ->whereIn('ad_account_id', $accounts->modelKeys())
            ->where('status', '!=', AdStatus::Removed)
            ->with(['ads.creative', 'targetProfile'])
            ->get();

        $ads = $campaigns->flatMap->ads;
        $lifetime = $this->lifetimeTotals($ads);
        $recent = AdMetric::query()
            ->whereIn('ad_id', $ads->pluck('id'))
            ->where('date', '>=', now()->subDays(30)->toDateString())
            ->orderBy('date')
            ->get()
            ->groupBy('ad_id');

        return [
            'today' => now()->toDateString(),
            'autonomy_level' => $project->ads_autonomy_level->value,
            'budget' => $this->budget($project),
            'policy' => [
                'exploration_share_of_budget_percent' => $this->settings->int('ads.policy.exploration_percent'),
                'max_daily_budget_increase_percent' => $this->settings->int('ads.policy.max_budget_increase_percent'),
                'max_new_campaigns_per_account_per_week' => $this->settings->int('ads.policy.max_new_campaigns_per_week'),
            ],
            'accounts' => $accounts->map(fn (AdAccount $account): array => $this->account(
                $account,
                $campaigns->where('ad_account_id', $account->id),
                $lifetime,
                $recent,
            ))->values()->all(),
            'creatives' => $this->creatives($project, $ads, $lifetime),
            'recent_decisions' => $this->decisions($project),
        ];
    }

    /**
     * The one budget every account draws from, and how the month is pacing.
     *
     * @return array<string, mixed>
     */
    private function budget(Project $project): array
    {
        $today = now();
        $daysLeft = $today->daysInMonth - $today->day + 1;
        $monthly = $project->ads_monthly_budget_micros;
        $spent = $project->adsSpentThisMonthMicros();
        $activeDaily = $project->adsActiveDailyBudgetMicros();
        $money = fn (int $micros): float => round($micros / 1_000_000, 2);

        return [
            'currency' => $project->ads_currency,
            'monthly' => $money($monthly),
            'spent_this_month' => $money($spent),
            'remaining' => $money(max(0, $monthly - $spent)),
            'days_left_in_month' => $daysLeft,
            'active_daily_budgets' => $money($activeDaily),
            'projected_month_spend' => $money($spent + $activeDaily * $daysLeft),
        ];
    }

    /**
     * @param  Collection<int, AdCampaign>  $campaigns
     * @param  Collection<int, array<string, float|int>>  $lifetime
     * @param  Collection<array-key, EloquentCollection<int, AdMetric>>  $recent
     * @return array<string, mixed>
     */
    private function account(AdAccount $account, Collection $campaigns, Collection $lifetime, Collection $recent): array
    {
        $money = fn (int $micros): float => round($micros / 1_000_000, 2);

        $accountTotals = $this->sum($campaigns->flatMap->ads->map(fn (Ad $ad): array => $lifetime->get($ad->id, $this->emptyTotals())));
        $tracksConversions = $account->has_conversion_tracking;
        $baseline = $tracksConversions ? $this->rate($accountTotals['conversions'], $accountTotals['clicks']) : $this->rate($accountTotals['clicks'], $accountTotals['impressions']);

        return [
            'id' => $account->id,
            'platform' => $account->platform->value,
            'name' => $account->name,
            'status' => $account->status->value,
            'usable' => $account->isUsable(),
            'currency' => $account->currency,
            'conversion_tracking' => $tracksConversions,
            'optimizing_for' => $tracksConversions ? 'conversions' : 'clicks (no conversion tracking on this account yet)',
            'copy_limits' => $account->platform->client()->capabilities()->toArray(),
            'spent_this_month' => $money($account->spentThisMonthMicros()),
            'active_daily_budgets' => $money($account->activeDailyBudgetMicros()),
            'lifetime' => $this->present($accountTotals),
            'campaigns' => $campaigns->map(fn (AdCampaign $campaign): array => [
                'id' => $campaign->id,
                'name' => $campaign->name,
                'status' => $campaign->status->value,
                'hypothesis' => $campaign->hypothesis,
                'target_profile' => $campaign->targetProfile?->name,
                'targeting' => $campaign->targeting,
                'daily_budget' => $money($campaign->daily_budget_micros),
                'running_for_days' => $campaign->launched_at === null ? null : (int) $campaign->launched_at->diffInDays(now()),
                'lifetime' => $this->present($this->sum($campaign->ads->map(fn (Ad $ad): array => $lifetime->get($ad->id, $this->emptyTotals())))),
                'ads' => $campaign->ads->where('status', '!=', AdStatus::Removed)->map(fn (Ad $ad): array => $this->ad(
                    $ad,
                    $lifetime->get($ad->id, $this->emptyTotals()),
                    $recent->get($ad->id, collect()),
                    $tracksConversions,
                    $baseline,
                ))->values()->all(),
            ])->values()->all(),
        ];
    }

    /**
     * @param  array<string, float|int>  $totals
     * @param  Collection<int, AdMetric>  $days
     * @return array<string, mixed>
     */
    private function ad(Ad $ad, array $totals, Collection $days, bool $tracksConversions, float $baseline): array
    {
        $lastWeek = $this->sum($days
            ->filter(fn (AdMetric $day): bool => $day->date->isAfter(now()->subDays(8)))
            ->map(fn (AdMetric $day): array => $this->totalsOf($day)));

        [$trials, $successes] = $tracksConversions
            ? [(int) $totals['clicks'], $totals['conversions']]
            : [(int) $totals['impressions'], (int) $totals['clicks']];

        [$ctrLow, $ctrHigh] = AdClassifier::interval((int) $totals['impressions'], (int) $totals['clicks']);
        [$cvrLow, $cvrHigh] = AdClassifier::interval((int) $totals['clicks'], $totals['conversions']);

        return [
            'id' => $ad->id,
            'creative_id' => $ad->ad_creative_id,
            'status' => $ad->status->value,
            'verdict' => $this->classifier->verdict($trials, $successes, (int) $totals['clicks'], (int) $totals['cost_micros'], $baseline)->value,
            'lifetime' => $this->present($totals),
            'last_7_days' => $this->present($lastWeek),
            'ctr_95_interval' => [round($ctrLow * 100, 2), round($ctrHigh * 100, 2)],
            'conversion_rate_95_interval' => [round($cvrLow * 100, 2), round($cvrHigh * 100, 2)],
            'account_baseline_rate_percent' => round($baseline * 100, 2),
            'daily' => $days
                ->filter(fn (AdMetric $day): bool => $day->date->isAfter(now()->subDays(self::SERIES_DAYS + 1)))
                ->map(fn (AdMetric $day): array => [
                    'date' => $day->date->toDateString(),
                    'impressions' => $day->impressions,
                    'clicks' => $day->clicks,
                    'cost' => round($day->cost_micros / 1_000_000, 2),
                    'conversions' => $day->conversions,
                ])->values()->all(),
        ];
    }

    /**
     * Each creative with its record per network, so a winner on one is
     * visibly untested on another.
     *
     * @param  Collection<int, Ad>  $ads
     * @param  Collection<int, array<string, float|int>>  $lifetime
     * @return array<int, array<string, mixed>>
     */
    private function creatives(Project $project, Collection $ads, Collection $lifetime): array
    {
        return AdCreative::query()
            ->where('project_id', $project->id)
            ->whereIn('id', $ads->pluck('ad_creative_id'))
            ->with('targetProfile')
            ->get()
            ->map(fn (AdCreative $creative): array => [
                'id' => $creative->id,
                'parent_id' => $creative->parent_id,
                'angle' => $creative->angle,
                'target_profile' => $creative->targetProfile?->name,
                'headlines' => $creative->headlines,
                'descriptions' => $creative->descriptions,
                'final_url' => $creative->final_url,
                'by_platform' => $ads
                    ->where('ad_creative_id', $creative->id)
                    ->groupBy(fn (Ad $ad): string => $ad->campaign->account->platform->value)
                    ->map(fn (Collection $group): array => $this->present($this->sum($group->map(fn (Ad $ad): array => $lifetime->get($ad->id, $this->emptyTotals())))))
                    ->all(),
            ])
            ->values()
            ->all();
    }

    /**
     * The agent's own memory: what was decided lately, whether it happened,
     * and for a decision on a campaign, how that campaign did in the week
     * before against since.
     *
     * @return array<int, array<string, mixed>>
     */
    private function decisions(Project $project): array
    {
        return AdDecision::query()
            ->where('project_id', $project->id)
            ->where('created_at', '>=', now()->subDays(30))
            ->latest()
            ->limit(40)
            ->get()
            ->map(function (AdDecision $decision): array {
                $since = $decision->applied_at ?? $decision->created_at;

                return [
                    'id' => $decision->id,
                    'type' => $decision->type->value,
                    'status' => $decision->status->value,
                    'campaign_id' => $decision->ad_campaign_id,
                    'ad_id' => $decision->ad_id,
                    'payload' => $decision->payload,
                    'reasoning' => $decision->reasoning,
                    'outcome' => $decision->outcome,
                    'date' => $decision->created_at?->toDateString(),
                    ...($decision->ad_campaign_id !== null && $decision->applied_at !== null ? [
                        'campaign_week_before' => $this->present($this->campaignTotals($decision->ad_campaign_id, $since->copy()->subDays(7), $since)),
                        'campaign_since' => $this->present($this->campaignTotals($decision->ad_campaign_id, $since, now())),
                    ] : []),
                ];
            })
            ->all();
    }

    /**
     * @param  Collection<int, Ad>  $ads
     * @return Collection<int, array<string, float|int>>
     */
    private function lifetimeTotals(Collection $ads): Collection
    {
        return AdMetric::query()
            ->whereIn('ad_id', $ads->pluck('id'))
            ->groupBy('ad_id')
            ->selectRaw('ad_id, sum(impressions) as impressions, sum(clicks) as clicks, sum(cost_micros) as cost_micros, sum(conversions) as conversions, sum(conversion_value) as conversion_value')
            ->get()
            ->mapWithKeys(fn (AdMetric $row): array => [$row->ad_id => $this->totalsOf($row)]);
    }

    /**
     * @return array<string, float|int>
     */
    private function campaignTotals(int $campaignId, CarbonInterface $from, CarbonInterface $to): array
    {
        $row = AdMetric::query()
            ->whereIn('ad_id', Ad::query()->where('ad_campaign_id', $campaignId)->select('id'))
            ->whereBetween('date', [$from->toDateString(), $to->toDateString()])
            ->selectRaw('coalesce(sum(impressions), 0) as impressions, coalesce(sum(clicks), 0) as clicks, coalesce(sum(cost_micros), 0) as cost_micros, coalesce(sum(conversions), 0) as conversions, coalesce(sum(conversion_value), 0) as conversion_value')
            ->first();

        return $row === null ? $this->emptyTotals() : $this->totalsOf($row);
    }

    /**
     * @return array<string, float|int>
     */
    private function totalsOf(AdMetric $row): array
    {
        return [
            'impressions' => (int) $row->impressions,
            'clicks' => (int) $row->clicks,
            'cost_micros' => (int) $row->cost_micros,
            'conversions' => (float) $row->conversions,
            'conversion_value' => (float) $row->conversion_value,
        ];
    }

    /**
     * @return array<string, float|int>
     */
    private function emptyTotals(): array
    {
        return ['impressions' => 0, 'clicks' => 0, 'cost_micros' => 0, 'conversions' => 0.0, 'conversion_value' => 0.0];
    }

    /**
     * @param  Collection<int, array<string, float|int>>  $rows
     * @return array<string, float|int>
     */
    private function sum(Collection $rows): array
    {
        return collect($this->emptyTotals())->map(fn (float|int $zero, string $key): float|int => $rows->sum($key))->all();
    }

    /**
     * Raw totals plus every ratio worth reading, null where the denominator
     * is zero rather than a misleading 0.
     *
     * @param  array<string, float|int>  $totals
     * @return array<string, float|int|null>
     */
    private function present(array $totals): array
    {
        $cost = $totals['cost_micros'] / 1_000_000;

        return [
            'impressions' => (int) $totals['impressions'],
            'clicks' => (int) $totals['clicks'],
            'cost' => round($cost, 2),
            'conversions' => round((float) $totals['conversions'], 2),
            'conversion_value' => round((float) $totals['conversion_value'], 2),
            'ctr_percent' => $totals['impressions'] > 0 ? round($totals['clicks'] / $totals['impressions'] * 100, 2) : null,
            'cpc' => $totals['clicks'] > 0 ? round($cost / $totals['clicks'], 2) : null,
            'conversion_rate_percent' => $totals['clicks'] > 0 ? round($totals['conversions'] / $totals['clicks'] * 100, 2) : null,
            'cost_per_conversion' => $totals['conversions'] > 0 ? round($cost / $totals['conversions'], 2) : null,
            'roas' => $cost > 0 && $totals['conversion_value'] > 0 ? round($totals['conversion_value'] / $cost, 2) : null,
        ];
    }

    private function rate(float|int $successes, float|int $trials): float
    {
        return $trials > 0 ? $successes / $trials : 0.0;
    }
}
