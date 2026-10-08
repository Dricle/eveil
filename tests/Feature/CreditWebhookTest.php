<?php

use App\Cloud\Listeners\GrantCreditsOnCheckout;
use App\Cloud\Models\CreditTransaction;
use App\Models\Organization;
use Laravel\Cashier\Events\WebhookReceived;

function checkoutCompleted(string $eventId, string $stripeCustomer, int $credits, ?int $amountCents = null, ?string $currency = null): WebhookReceived
{
    $session = ['customer' => $stripeCustomer, 'metadata' => ['credits' => (string) $credits]];

    if ($amountCents !== null) {
        $session['amount_total'] = $amountCents;
        $session['currency'] = $currency;
    }

    return new WebhookReceived([
        'id' => $eventId,
        'type' => 'checkout.session.completed',
        'data' => ['object' => $session],
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

it('records what was actually paid, so revenue is readable without Stripe', function () {
    $organization = Organization::factory()->create();
    $organization->forceFill(['stripe_id' => 'cus_6'])->save();

    (new GrantCreditsOnCheckout)->handle(checkoutCompleted('evt_6', 'cus_6', 20000, 2000, 'eur'));

    expect(CreditTransaction::sole())
        ->amount_cents->toBe(2000)
        ->currency->toBe('eur');
});

/**
 * The amount Stripe collected, not the one we asked for: the two differ on a
 * discount or a currency conversion, and the ledger has to agree with the
 * payout rather than with our intention.
 */
it('records the amount Stripe reports rather than re-deriving it from the credits', function () {
    $organization = Organization::factory()->create();
    $organization->forceFill(['stripe_id' => 'cus_7'])->save();

    (new GrantCreditsOnCheckout)->handle(checkoutCompleted('evt_7', 'cus_7', 20000, 1500, 'usd'));

    expect(CreditTransaction::sole())->amount_cents->toBe(1500);
});

/**
 * Null is not zero. A session that collected nothing must not land in the
 * ledger as a confirmed zero-euro sale, which would average into every report.
 */
it('leaves the amount null when the session carries no total', function () {
    $organization = Organization::factory()->create();
    $organization->forceFill(['stripe_id' => 'cus_8'])->save();

    (new GrantCreditsOnCheckout)->handle(checkoutCompleted('evt_8', 'cus_8', 7000));

    expect(CreditTransaction::sole())
        ->amount_cents->toBeNull()
        ->currency->toBeNull();
});

it('ignores a session with no credits in its metadata', function () {
    $organization = Organization::factory()->create();
    $organization->forceFill(['stripe_id' => 'cus_5'])->save();

    (new GrantCreditsOnCheckout)->handle(checkoutCompleted('evt_5', 'cus_5', 0));

    expect(CreditTransaction::count())->toBe(0);
});
