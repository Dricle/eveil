<?php

namespace App\Cloud\Listeners;

use App\Models\Organization;
use Laravel\Cashier\Events\WebhookReceived;

/**
 * The other half of `PaymentMethodController`'s setup-mode Checkout Session:
 * Stripe's webhook, not the browser's return trip, is what actually saves
 * the card — the user's tab can close before the redirect completes, and the
 * webhook still arrives.
 *
 * The session payload carries `setup_intent` as a bare ID, never expanded,
 * so the SetupIntent is fetched to read the payment method it resulted in.
 */
class SavePaymentMethodOnSetup
{
    public function handle(WebhookReceived $event): void
    {
        if ($event->payload['type'] !== 'checkout.session.completed') {
            return;
        }

        $session = $event->payload['data']['object'];

        if (($session['mode'] ?? null) !== 'setup') {
            return;
        }

        $stripeId = $session['customer'] ?? null;
        $setupIntentId = $session['setup_intent'] ?? null;

        if ($stripeId === null || $setupIntentId === null) {
            return;
        }

        $organization = Organization::query()->where('stripe_id', $stripeId)->first();

        if ($organization === null) {
            return;
        }

        $paymentMethod = $organization->stripe()->setupIntents->retrieve($setupIntentId)->payment_method;

        if ($paymentMethod !== null) {
            $organization->updateDefaultPaymentMethod($paymentMethod);
        }
    }
}
