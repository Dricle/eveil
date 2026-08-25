<?php

namespace App\Cloud\Actions;

use App\Cloud\Models\CreditTransaction;
use App\Models\Organization;
use App\Support\Settings;

/**
 * What a brand new cloud organization starts with, seeded once (ADR-024).
 * Never called on self-hosted — there is nothing calling it there,
 * `OrganizationController::store()` only reaches for this on the cloud
 * edition.
 */
class GrantTrialCredits
{
    public function __construct(private Settings $settings) {}

    public function handle(Organization $organization): void
    {
        $credits = $this->settings->int('billing.trial_credits');

        $organization->forceFill(['credits_balance' => $credits])->save();

        CreditTransaction::create([
            'organization_id' => $organization->id,
            'type' => 'grant_trial',
            'credits' => $credits,
        ]);
    }
}
