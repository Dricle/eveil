<?php

namespace App\Actions;

use App\Cloud\Actions\GrantTrialCredits;
use App\Enums\OrganizationRole;
use App\Models\Organization;
use App\Models\User;

/**
 * The one place a NEW organization comes into being, whether that is a
 * user's first (via `CreateAccount`, at signup) or an additional one they
 * add later (`OrganizationController::store()`). Trial credits go with it
 * unconditionally: the two call sites drifted once already when this lived
 * as a copy-pasted conditional in each - see the "no trial credits at
 * signup" bug this replaced.
 */
class CreateOrganization
{
    public function __construct(private GrantTrialCredits $grantTrialCredits) {}

    public function handle(string $name, User $owner): Organization
    {
        $organization = Organization::create(['name' => $name]);

        $organization->users()->attach($owner, ['role' => OrganizationRole::Owner->value]);

        if (config('eveil.edition') === 'cloud') {
            $this->grantTrialCredits->handle($organization);
        }

        return $organization;
    }
}
