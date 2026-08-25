<?php

use App\Cloud\Listeners\GrantCreditsOnCheckout;
use App\Cloud\Models\CreditTransaction;
use App\Models\Organization;
use Laravel\Cashier\Events\WebhookReceived;

function checkoutCompleted(string $eventId, string $stripeCustomer, int $credits): WebhookReceived
{
    return new WebhookReceived([
        'id' => $eventId,
        'type' => 'checkout.session.completed',
        'data' => ['object' => ['customer' => $stripeCustomer, 'metadata' => ['credits' => (string) $credits]]],
    ]);
}

it('grants the credits locked into the session metadata on checkout completion', function () {
    $organization = Organization::factory()->create();
    $organization->forceFill(['stripe_id' => 'cus_1'])->save();

    (new GrantCreditsOnCheckout)->handle(checkoutCompleted('evt_1', 'cus_1', 7000));

    expect($organization->fresh()->credits_balance)->toBe(7000)
        ->and(CreditTransaction::sole())
        ->type->toBe('grant_purchase')
        ->credits->toBe(7000)
        ->stripe_event_id->toBe('evt_1');
});

it('grants a top-up additive with any existing balance', function () {
    $organization = Organization::factory()->create();
    $organization->forceFill(['stripe_id' => 'cus_2', 'credits_balance' => 150])->save();

    (new GrantCreditsOnCheckout)->handle(checkoutCompleted('evt_2', 'cus_2', 10000));

    expect($organization->fresh()->credits_balance)->toBe(10150);
});

it('never grants the same webhook event twice', function () {
    $organization = Organization::factory()->create();
    $organization->forceFill(['stripe_id' => 'cus_3'])->save();
    $listener = new GrantCreditsOnCheckout;

    $listener->handle(checkoutCompleted('evt_3', 'cus_3', 7000));
    $listener->handle(checkoutCompleted('evt_3', 'cus_3', 7000));

    expect($organization->fresh()->credits_balance)->toBe(7000)
        ->and(CreditTransaction::count())->toBe(1);
});

it('ignores an event for an unknown customer', function () {
    (new GrantCreditsOnCheckout)->handle(checkoutCompleted('evt_4', 'cus_ghost', 7000));

    expect(CreditTransaction::count())->toBe(0);
});

it('ignores a session with no credits in its metadata', function () {
    $organization = Organization::factory()->create();
    $organization->forceFill(['stripe_id' => 'cus_5'])->save();

    (new GrantCreditsOnCheckout)->handle(checkoutCompleted('evt_5', 'cus_5', 0));

    expect(CreditTransaction::count())->toBe(0);
});
