<?php

use App\Enums\OrganizationRole;
use App\Models\Organization;
use App\Models\Project;
use App\Models\User;

/**
 * Starting a checkout, configuring auto top-up and saving a payment method
 * all spend or enable spending the organization's money; any member could
 * reach every one of them before this. Only the refusal is covered here:
 * confirming an owner/admin is correctly let through needs an actual Stripe
 * call, which needs a live test-mode key (same gap noted elsewhere, e.g.
 * `PaymentMethodWebhookTest`) - nothing here should ever touch the network.
 */
function memberWithRole(OrganizationRole $role): array
{
    $organization = Organization::factory()->create();
    $user = User::factory()->create();
    $organization->users()->attach($user, ['role' => $role->value]);
    $project = Project::factory()->for($organization)->create();

    // Owner/Admin see every project in their organization; a plain member
    // needs an explicit grant, or `SetCurrentProject` finds nothing visible
    // and redirects to project creation before `authorize()` ever runs.
    if ($role === OrganizationRole::Member) {
        $user->projects()->attach($project);
    }

    return [$organization, $user];
}

it('refuses a plain member starting a checkout', function () {
    [, $user] = memberWithRole(OrganizationRole::Member);

    $this->actingAs($user)
        ->post(route('settings.organization.billing.checkout'), ['amount_cents' => 2000])
        ->assertForbidden();
});

it('refuses a plain member configuring auto top-up', function () {
    [, $user] = memberWithRole(OrganizationRole::Member);

    $this->actingAs($user)
        ->put(route('settings.organization.billing.auto-topup'), [
            'auto_topup_threshold' => 100,
            'auto_topup_amount_cents' => 2000,
        ])
        ->assertForbidden();
});

it('refuses a plain member saving a payment method', function () {
    [, $user] = memberWithRole(OrganizationRole::Member);

    $this->actingAs($user)
        ->get(route('settings.organization.billing.payment-method.create'))
        ->assertForbidden();
});
