<?php

namespace App\Actions;

use App\Enums\AdStatus;
use App\Models\AdAccount;
use App\Services\Ads\AdProposals;

/**
 * The hard stop. Once an account has spent its month, or its budget was set
 * to zero, every campaign Eveil runs there is paused, whatever any agent or
 * person decided before. Pure code, run after every metrics sync, never left
 * to a prompt.
 *
 * It goes through `AdProposals` like any decision, so the journal says why
 * the campaigns stopped. A pause never waits for approval.
 */
class EnforceAdBudget
{
    public function __construct(private AdProposals $proposals) {}

    public function handle(AdAccount $account): void
    {
        $spent = $account->spentThisMonthMicros();

        if ($account->monthly_budget_micros > 0 && $spent < $account->monthly_budget_micros) {
            return;
        }

        $reason = $account->monthly_budget_micros === 0
            ? 'The monthly budget is set to zero.'
            : 'The monthly budget is spent.';

        foreach ($account->campaigns()->where('status', AdStatus::Active)->pluck('id') as $campaignId) {
            $this->proposals->pauseCampaign($account->project, null, $campaignId, $reason);
        }
    }
}
