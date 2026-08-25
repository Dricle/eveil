<?php

use App\Cloud\Listeners\SavePaymentMethodOnSetup;
use App\Models\Organization;
use Laravel\Cashier\Events\WebhookReceived;

/**
 * The success path (Stripe actually confirms a SetupIntent and hands back a
 * payment method) needs a real Stripe test-mode call — same gap noted for
 * checkout elsewhere (tracked as a GitHub issue). What is covered here is
 * every guard that decides whether to make that call at all, none of which
 * should ever touch the network on their own.
 */
function setupSessionCompleted(string $stripeCustomer, ?string $setupIntent, string $mode = 'setup'): WebhookReceived
{
    return new WebhookReceived([
        'id' => 'evt_setup_1',
        'type' => 'checkout.session.completed',
        'data' => ['object' => ['customer' => $stripeCustomer, 'mode' => $mode, 'setup_intent' => $setupIntent]],
    ]);
}

it('ignores anything other than a completed checkout session', function () {
    $organization = Organization::factory()->create();
    $organization->forceFill(['stripe_id' => 'cus_1'])->save();

    (new SavePaymentMethodOnSetup)->handle(new WebhookReceived([
        'id' => 'evt_1', 'type' => 'invoice.payment_succeeded', 'data' => ['object' => ['customer' => 'cus_1']],
    ]));

    expect($organization->fresh()->pm_type)->toBeNull();
});

it('ignores a checkout session that is not in setup mode', function () {
    $organization = Organization::factory()->create();
    $organization->forceFill(['stripe_id' => 'cus_2'])->save();

    (new SavePaymentMethodOnSetup)->handle(setupSessionCompleted('cus_2', 'seti_123', mode: 'payment'));

    expect($organization->fresh()->pm_type)->toBeNull();
});

it('ignores a setup session for an unknown customer', function () {
    (new SavePaymentMethodOnSetup)->handle(setupSessionCompleted('cus_ghost', 'seti_123'));

    expect(Organization::query()->where('stripe_id', 'cus_ghost')->exists())->toBeFalse();
});

it('ignores a setup session with no setup intent id', function () {
    $organization = Organization::factory()->create();
    $organization->forceFill(['stripe_id' => 'cus_3'])->save();

    (new SavePaymentMethodOnSetup)->handle(setupSessionCompleted('cus_3', null));

    expect($organization->fresh()->pm_type)->toBeNull();
});
