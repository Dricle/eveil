<?php

namespace App\Cloud\Actions;

use App\Cloud\Models\CreditTransaction;
use App\Models\Organization;
use App\Support\Settings;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Laravel\Cashier\PaymentMethod;
use Throwable;

/**
 * Checked after every debit (`CreditSpendGuard::charge()`): the pay-as-you-go
 * answer to a subscription's auto-renewal, an off-session charge against the
 * card already on file rather than a redirect the customer has to complete.
 *
 * Never called from inside `Organization::debit()`'s transaction: the Stripe
 * call is a network round-trip and must never hold a DB lock while it runs.
 */
class AutoTopUp
{
    public function __construct(private Settings $settings) {}

    public function maybeTrigger(Organization $organization): void
    {
        if ($organization->auto_topup_threshold === null || $organization->auto_topup_amount_cents === null) {
            return;
        }

        if ($organization->credits_balance > $organization->auto_topup_threshold) {
            return;
        }

        if (! $organization->hasDefaultPaymentMethod()) {
            return;
        }

        // The atomic claim, not a prior SELECT: two agent calls can cross
        // the threshold within moments of each other, and only one may
        // reach for the card.
        if (! $organization->claimAutoTopUpLock()) {
            return;
        }

        $amountCents = $organization->auto_topup_amount_cents;
        $credits = intdiv($amountCents * $this->settings->int('billing.credits_per_dollar'), 100);

        // `defaultPaymentMethod()` returns `Cashier\PaymentMethod|Stripe\Card
        // |Stripe\BankAccount` — only Cashier's own wrapper lacks a declared
        // `id` (it proxies to Stripe via `__get`), so it is the one branch
        // that needs unwrapping to the raw Stripe object first.
        $paymentMethod = $organization->defaultPaymentMethod();
        $paymentMethodId = $paymentMethod instanceof PaymentMethod
            ? $paymentMethod->asStripePaymentMethod()->id
            : $paymentMethod->id;

        try {
            $payment = $organization->charge($amountCents, $paymentMethodId, ['off_session' => true]);
        } catch (Throwable $e) {
            // A card decline here is routine, not exceptional: the customer
            // finds out from a low balance, same as any other failed
            // recharge. The lock's cooldown is what stops this from retrying
            // on every single debit until the card is fixed.
            Log::warning('Auto top-up charge failed.', ['organization_id' => $organization->id, 'error' => $e->getMessage()]);

            return;
        }

        $stripePaymentIntentId = $payment->asStripePaymentIntent()->id;

        DB::transaction(function () use ($organization, $credits, $stripePaymentIntentId): void {
            $organization->increment('credits_balance', $credits);

            CreditTransaction::create([
                'organization_id' => $organization->id,
                'type' => 'grant_purchase',
                'credits' => $credits,
                'stripe_event_id' => $stripePaymentIntentId,
            ]);
        });
    }
}
