<?php

namespace App\Actions;

use App\Enums\AdAccountStatus;
use App\Enums\AdDecisionStatus;
use App\Enums\AdDecisionType;
use App\Enums\AdStatus;
use App\Models\Ad;
use App\Models\AdCampaign;
use App\Models\AdDecision;
use App\Services\Ads\AdGuardrails;
use App\Services\Ads\AdPlatformException;

/**
 * Carries one decision out on its network. The only place a network is ever
 * told to change something, whoever decided it.
 *
 * Safe to run twice: a decision already settled is skipped, and a launch
 * that created its campaign but died before enabling it picks up from there
 * instead of creating a second one.
 */
class ApplyAdDecision
{
    public function __construct(private AdGuardrails $guardrails) {}

    public function handle(AdDecision $decision): void
    {
        $pending = $decision->status === AdDecisionStatus::Approved
            || ($decision->status === AdDecisionStatus::Proposed && ! $decision->needsApproval());

        if (! $pending) {
            return;
        }

        // Checked again: the account may have spent since it was proposed.
        $refusal = $this->guardrails->refusal($decision);

        if ($refusal !== null) {
            $decision->update(['status' => AdDecisionStatus::Blocked, 'outcome' => $refusal]);
            $decision->discardDrafts();

            return;
        }

        try {
            $outcome = null;

            match ($decision->type) {
                AdDecisionType::LaunchCampaign => $this->launch($decision->campaign),
                AdDecisionType::AddAd => $outcome = $this->addAd($decision->ad),
                AdDecisionType::PauseAd => $this->pauseAd($decision->ad),
                AdDecisionType::PauseCampaign => $this->pauseCampaign($decision->campaign),
                AdDecisionType::SetDailyBudget => $this->setDailyBudget($decision->campaign, (int) $decision->payload['daily_budget_micros']),
            };
        } catch (AdPlatformException $e) {
            $decision->update(['status' => AdDecisionStatus::Failed, 'outcome' => $e->getMessage()]);

            if ($e->revoked) {
                $decision->account?->update(['status' => AdAccountStatus::Error, 'last_error' => $e->getMessage()]);
            }

            return;
        }

        $decision->update(['status' => AdDecisionStatus::Applied, 'outcome' => $outcome, 'applied_at' => now()]);
    }

    private function launch(AdCampaign $campaign): void
    {
        $client = $campaign->account->platform->client();

        if ($campaign->external_id === null) {
            $launched = $client->createCampaign($campaign);

            $campaign->update([
                'external_id' => $launched->externalId,
                'external_ref' => $launched->ref,
                'status' => AdStatus::Paused,
            ]);

            foreach ($launched->adExternalIds as $adId => $externalId) {
                Ad::query()->whereKey($adId)->update(['external_id' => $externalId, 'status' => AdStatus::Active]);
            }
        }

        $client->setCampaignStatus($campaign, AdStatus::Active);
        $campaign->update(['status' => AdStatus::Active, 'launched_at' => now()]);
    }

    private function addAd(Ad $ad): ?string
    {
        // A campaign not launched yet takes its draft ads along when it is.
        if ($ad->campaign->external_id === null) {
            return 'Joins the campaign when it launches.';
        }

        $ad->update([
            'external_id' => $ad->campaign->account->platform->client()->createAd($ad),
            'status' => AdStatus::Active,
        ]);

        return null;
    }

    private function pauseAd(Ad $ad): void
    {
        $ad->campaign->account->platform->client()->setAdStatus($ad, AdStatus::Paused);
        $ad->update(['status' => AdStatus::Paused]);
    }

    private function pauseCampaign(AdCampaign $campaign): void
    {
        $campaign->account->platform->client()->setCampaignStatus($campaign, AdStatus::Paused);
        $campaign->update(['status' => AdStatus::Paused]);
    }

    private function setDailyBudget(AdCampaign $campaign, int $micros): void
    {
        if ($campaign->external_id !== null) {
            $campaign->account->platform->client()->setDailyBudget($campaign, $micros);
        }

        $campaign->update(['daily_budget_micros' => $micros]);
    }
}
