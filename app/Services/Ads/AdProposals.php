<?php

namespace App\Services\Ads;

use App\Enums\AdDecisionStatus;
use App\Enums\AdDecisionType;
use App\Enums\AdStatus;
use App\Jobs\ApplyAdDecision;
use App\Models\Ad;
use App\Models\AdAccount;
use App\Models\AdCampaign;
use App\Models\AdCreative;
use App\Models\AdDecision;
use App\Models\Project;
use App\Support\Url;
use Illuminate\Support\Facades\DB;

/**
 * The one way a decision comes into existence, for the agent's tools and for
 * the budget stop alike. Each method validates what it was given, stores the
 * drafts a launch or a new ad needs, writes the decision, runs the
 * guardrails, and either queues it or leaves it for a person.
 *
 * Every method returns a sentence for the agent: a tool's answer is the only
 * feedback it gets inside its run, so a refusal says what would pass.
 *
 * Lookups are constrained to the project by hand: the agent passes ids, and
 * an id from another project must read as unknown, whatever `CurrentProject`
 * holds when this runs.
 */
class AdProposals
{
    public function __construct(private AdGuardrails $guardrails) {}

    /**
     * @param  array<string, mixed>  $targeting  keywords, negative_keywords, countries, languages: lists of strings
     * @param  array<int, mixed>  $ads  each either `creative_id` (reuse as is) or the copy for a new creative
     */
    public function launchCampaign(Project $project, ?int $agentRunId, int $accountId, string $name, string $hypothesis, ?int $targetProfileId, float $dailyBudget, array $targeting, array $ads, string $reasoning): string
    {
        $account = AdAccount::query()->where('project_id', $project->id)->find($accountId);

        if ($account === null) {
            return "No ad account {$accountId} on this project.";
        }

        // As the model sent them: keep only lists of non-empty strings.
        $targeting = collect(['keywords', 'negative_keywords', 'countries', 'languages'])
            ->mapWithKeys(fn (string $key): array => [$key => self::strings($targeting[$key] ?? [])])
            ->all();

        if ($dailyBudget <= 0) {
            return 'Give a daily budget above zero.';
        }

        if ($ads === []) {
            return 'A campaign needs at least one ad.';
        }

        if (in_array('keywords', $account->platform->client()->capabilities()->targeting, true) && empty($targeting['keywords'])) {
            return "{$account->platform->label()} campaigns need keywords.";
        }

        $creatives = [];

        foreach (array_values($ads) as $index => $spec) {
            $creative = $this->creative($project, $account, is_array($spec) ? $spec : [], $targetProfileId);

            if (is_string($creative)) {
                return 'Ad '.($index + 1).": {$creative}";
            }

            $creatives[] = $creative;
        }

        $campaign = DB::transaction(function () use ($project, $account, $name, $hypothesis, $targetProfileId, $dailyBudget, $targeting, $creatives): AdCampaign {
            $campaign = AdCampaign::create([
                'project_id' => $project->id,
                'ad_account_id' => $account->id,
                'target_profile_id' => $this->targetProfileId($project, $targetProfileId),
                'name' => $name,
                'hypothesis' => $hypothesis,
                'targeting' => $targeting,
                'daily_budget_micros' => self::micros($dailyBudget),
                'status' => AdStatus::Draft,
            ]);

            foreach ($creatives as $creative) {
                $creative->save();

                Ad::create([
                    'project_id' => $project->id,
                    'ad_campaign_id' => $campaign->id,
                    'ad_creative_id' => $creative->id,
                    'status' => AdStatus::Draft,
                ]);
            }

            return $campaign;
        });

        return $this->propose($project, $agentRunId, AdDecisionType::LaunchCampaign, $reasoning, $account, $campaign);
    }

    /**
     * A new ad in a campaign: a variant of what runs, or a creative proven
     * elsewhere tried on this network as is.
     *
     * @param  array<string, mixed>  $spec
     */
    public function addAd(Project $project, ?int $agentRunId, int $campaignId, array $spec, string $reasoning): string
    {
        $campaign = $this->campaign($project, $campaignId);

        if ($campaign === null) {
            return "No campaign {$campaignId} on this project that is still running or waiting to launch.";
        }

        $creative = $this->creative($project, $campaign->account, $spec, $campaign->target_profile_id);

        if (is_string($creative)) {
            return $creative;
        }

        $creative->save();

        $ad = Ad::create([
            'project_id' => $project->id,
            'ad_campaign_id' => $campaign->id,
            'ad_creative_id' => $creative->id,
            'status' => AdStatus::Draft,
        ]);

        return $this->propose($project, $agentRunId, AdDecisionType::AddAd, $reasoning, $campaign->account, $campaign, $ad);
    }

    public function pauseAd(Project $project, ?int $agentRunId, int $adId, string $reasoning): string
    {
        $ad = Ad::query()->where('project_id', $project->id)->where('status', AdStatus::Active)->find($adId);

        if ($ad === null) {
            return "No running ad {$adId} on this project.";
        }

        return $this->propose($project, $agentRunId, AdDecisionType::PauseAd, $reasoning, $ad->campaign->account, $ad->campaign, $ad);
    }

    public function pauseCampaign(Project $project, ?int $agentRunId, int $campaignId, string $reasoning): string
    {
        $campaign = AdCampaign::query()->where('project_id', $project->id)->where('status', AdStatus::Active)->find($campaignId);

        if ($campaign === null) {
            return "No running campaign {$campaignId} on this project.";
        }

        // Every account's sync runs the budget stop for the whole project:
        // one pause on its way is enough.
        $pending = AdDecision::query()
            ->where('ad_campaign_id', $campaign->id)
            ->where('type', AdDecisionType::PauseCampaign)
            ->whereIn('status', [AdDecisionStatus::Proposed, AdDecisionStatus::Approved])
            ->first();

        if ($pending !== null) {
            return "Campaign {$campaignId} is already being paused (decision {$pending->id}).";
        }

        return $this->propose($project, $agentRunId, AdDecisionType::PauseCampaign, $reasoning, $campaign->account, $campaign);
    }

    public function setDailyBudget(Project $project, ?int $agentRunId, int $campaignId, float $dailyBudget, string $reasoning): string
    {
        $campaign = $this->campaign($project, $campaignId);

        if ($campaign === null) {
            return "No campaign {$campaignId} on this project that is still running or waiting to launch.";
        }

        if ($dailyBudget <= 0) {
            return 'Give a daily budget above zero. To stop a campaign, pause it.';
        }

        return $this->propose($project, $agentRunId, AdDecisionType::SetDailyBudget, $reasoning, $campaign->account, $campaign, payload: [
            'daily_budget_micros' => self::micros($dailyBudget),
        ]);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function propose(Project $project, ?int $agentRunId, AdDecisionType $type, string $reasoning, AdAccount $account, AdCampaign $campaign, ?Ad $ad = null, array $payload = []): string
    {
        $decision = AdDecision::create([
            'project_id' => $project->id,
            'ad_account_id' => $account->id,
            'ad_campaign_id' => $campaign->id,
            'ad_id' => $ad?->id,
            'agent_run_id' => $agentRunId,
            'type' => $type,
            'payload' => $payload,
            'reasoning' => $reasoning,
            'status' => AdDecisionStatus::Proposed,
        ]);

        $refusal = $this->guardrails->refusal($decision);

        if ($refusal !== null) {
            $decision->update(['status' => AdDecisionStatus::Blocked, 'outcome' => $refusal]);
            $decision->discardDrafts();

            return "Blocked (decision {$decision->id}): {$refusal}";
        }

        if ($decision->needsApproval()) {
            return "Proposed as decision {$decision->id}. It waits for the user's approval ({$project->ads_autonomy_level->value} autonomy).";
        }

        ApplyAdDecision::dispatch($decision);

        return "Decision {$decision->id} is being applied.";
    }

    /**
     * An unsaved creative from the agent's spec, or the reason it cannot be
     * one. Unsaved so a launch with one bad ad stores nothing at all.
     *
     * @param  array<string, mixed>  $spec
     */
    private function creative(Project $project, AdAccount $account, array $spec, ?int $targetProfileId): AdCreative|string
    {
        if (isset($spec['creative_id'])) {
            $existing = AdCreative::query()->where('project_id', $project->id)->find((int) $spec['creative_id']);

            if ($existing === null) {
                return "No creative {$spec['creative_id']} on this project.";
            }

            // Reused as is: the same row, so its record spans networks.
            $problem = $account->platform->client()->capabilities()->problemsWith($existing->headlines, $existing->descriptions);

            return $problem === null
                ? $existing
                : "Creative {$existing->id} does not fit {$account->platform->label()}: {$problem} Write an adapted copy with parent_id {$existing->id} instead.";
        }

        $headlines = self::strings($spec['headlines'] ?? []);
        $descriptions = self::strings($spec['descriptions'] ?? []);
        $finalUrl = (string) ($spec['final_url'] ?? $project->url);

        $problem = $account->platform->client()->capabilities()->problemsWith($headlines, $descriptions);

        if ($problem !== null) {
            return $problem;
        }

        // Ads only ever send people to the product itself.
        $productHost = Url::host($project->url);
        $host = Url::host($finalUrl);

        if ($host === null || ($host !== $productHost && ! str_ends_with($host, ".{$productHost}"))) {
            return "final_url must be on the product's own site ({$productHost}).";
        }

        $parentId = isset($spec['parent_id'])
            ? AdCreative::query()->where('project_id', $project->id)->whereKey((int) $spec['parent_id'])->value('id')
            : null;

        return new AdCreative([
            'project_id' => $project->id,
            'parent_id' => $parentId,
            'target_profile_id' => $this->targetProfileId($project, $targetProfileId),
            'angle' => trim((string) ($spec['angle'] ?? '')),
            'headlines' => $headlines,
            'descriptions' => $descriptions,
            'final_url' => $finalUrl,
        ]);
    }

    private function campaign(Project $project, int $campaignId): ?AdCampaign
    {
        return AdCampaign::query()
            ->where('project_id', $project->id)
            ->whereIn('status', [AdStatus::Draft, AdStatus::Active, AdStatus::Paused])
            ->find($campaignId);
    }

    private function targetProfileId(Project $project, ?int $targetProfileId): ?int
    {
        return $targetProfileId === null ? null : $project->targetProfiles()->whereKey($targetProfileId)->value('id');
    }

    /**
     * @return list<string>
     */
    private static function strings(mixed $values): array
    {
        return array_values(array_filter(
            array_map(fn (mixed $value): string => is_scalar($value) ? trim((string) $value) : '', is_array($values) ? $values : []),
            fn (string $value): bool => $value !== '',
        ));
    }

    public static function micros(float $amount): int
    {
        return (int) round($amount * 1_000_000);
    }
}
