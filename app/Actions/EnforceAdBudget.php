<?php

namespace App\Actions;

use App\Enums\AdStatus;
use App\Models\AdCampaign;
use App\Models\Project;
use App\Services\Ads\AdProposals;

/**
 * The hard stop. Once the project has spent its month, or its budget was set
 * to zero, or ads were switched off, every campaign Eveil runs is paused, on every account, whatever
 * any agent or person decided before. So is every campaign on an account the
 * budget may no longer reach: switched off, or billed in another currency.
 * Pure code, run after every metrics sync, never left to a prompt.
 *
 * It goes through `AdProposals` like any decision, so the journal says why
 * the campaigns stopped. A pause never waits for approval.
 */
class EnforceAdBudget
{
    public function __construct(private AdProposals $proposals) {}

    public function handle(Project $project): void
    {
        $budget = $project->ads_monthly_budget_micros;
        $exhausted = $budget === 0 || $project->adsSpentThisMonthMicros() >= $budget;

        $running = AdCampaign::query()
            ->whereIn('ad_account_id', $project->adAccounts()->select('id'))
            ->where('status', AdStatus::Active)
            ->with('account.project')
            ->get();

        foreach ($running as $campaign) {
            $reason = match (true) {
                ! $project->ads_enabled => 'Ads are switched off for this project.',
                $budget === 0 => 'The monthly ads budget is set to zero.',
                $exhausted => 'The monthly ads budget is spent.',
                ! $campaign->account->isUsable() => 'Eveil may no longer spend on this ad account.',
                default => null,
            };

            if ($reason !== null) {
                $this->proposals->pauseCampaign($project, null, $campaign->id, $reason);
            }
        }
    }
}
