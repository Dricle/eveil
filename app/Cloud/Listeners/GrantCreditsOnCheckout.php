<?php

namespace App\Cloud\Listeners;

use App\Cloud\Models\CreditTransaction;
use App\Models\Organization;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Laravel\Cashier\Events\WebhookReceived;

/**
 * A completed Checkout session: a pay-as-you-go top-up, manual or (soon)
 * embedded - never a subscription renewal, there is no subscription. The
 * credit count is read from `metadata.credits`, stamped by `StartCheckout`
 * at the moment the customer saw the button: this listener never recomputes
 * it from the current rate, which may have moved since.
 */
class GrantCreditsOnCheckout
{
    public function handle(WebhookReceived $event): void
    {
        if ($event->payload['type'] !== 'checkout.session.completed') {
            return;
        }

        $session = $event->payload['data']['object'];
        $credits = (int) ($session['metadata']['credits'] ?? 0);
        $stripeId = $session['customer'] ?? null;

        if ($credits <= 0 || $stripeId === null) {
            return;
        }

        // `amount_total` is null on a session that collected nothing (a saved
        // payment method, a fully discounted total), which is not the same
        // fact as "zero was paid" - keep it null rather than recording a 0.
        $amountCents = isset($session['amount_total']) ? (int) $session['amount_total'] : null;
        $currency = isset($session['currency']) ? (string) $session['currency'] : null;

        $organization = Organization::query()->where('stripe_id', $stripeId)->first();

        if ($organization === null) {
            return;
        }

        /**
         * The `stripe_event_id` unique index is the idempotency guard, not a
         * SELECT beforehand: two workers racing the same retried webhook
         * could both pass a check-then-insert. A duplicate event's INSERT
         * fails the unique constraint, which aborts this whole transaction
         * on Postgres - so the balance update inside it is rolled back
         * together with the ledger row, and a caught `QueryException` means
         * "already processed", not an error.
         */
        try {
            DB::transaction(function () use ($organization, $credits, $event): void {
                $organization->increment('credits_balance', $credits, ['out_of_credit_notified_at' => null]);

                CreditTransaction::create([
                    'organization_id' => $organization->id,
                    'type' => 'grant_purchase',
                    'credits' => $credits,
                    // What Stripe says was collected, not what we asked for:
                    // a discount or a currency conversion makes the two differ,
                    // and the ledger has to agree with the payout.
                    'amount_cents' => $amountCents,
                    'currency' => $currency,
                    'stripe_event_id' => $event->payload['id'],
                ]);
            });
        } catch (QueryException) {
            // Already processed. Stripe retries a webhook until it gets a
            // 2xx; this is what makes that safe to grant nothing twice.
        }
    }
}
