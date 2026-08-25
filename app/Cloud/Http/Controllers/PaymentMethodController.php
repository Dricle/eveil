<?php

namespace App\Cloud\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Support\CurrentProject;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response;

/**
 * A saved card is what makes auto top-up possible: an off-session charge
 * needs a payment method already on file, since nobody is at the keyboard
 * when the wallet crosses the threshold.
 *
 * Stripe-hosted, not a form we render: a Checkout Session in `setup` mode
 * collects and saves a card with no charge attached, on Stripe's own page —
 * same mechanism `StartCheckout` uses for a top-up, just with no line items.
 * `SavePaymentMethodOnSetup` is what actually stores it, off the
 * `checkout.session.completed` webhook once Stripe confirms it.
 */
class PaymentMethodController extends Controller
{
    public function __construct(private CurrentProject $currentProject) {}

    public function create(): Response
    {
        $organization = $this->currentProject->organization();

        $checkout = $organization->checkout([], [
            'mode' => 'setup',
            // Required by Stripe for `setup` mode specifically: with no
            // `payment_method_types` restriction, Checkout's dynamic payment
            // methods need a currency to know which ones are even eligible
            // to be saved for later, even though nothing is charged here.
            'currency' => $organization->preferredCurrency(),
            'success_url' => route('settings.organization.billing.edit', ['checkout' => 'payment-method-saved']),
            'cancel_url' => route('settings.organization.billing.edit'),
        ]);

        // The link to here is marked `external` in the Vue template, so this
        // request should never actually carry Inertia's headers — but a
        // plain redirect to Stripe's page is not an Inertia response, so
        // wrap it the same way as `CheckoutController` in case a future
        // caller reaches this route through an Inertia visit anyway.
        return Inertia::location($checkout->redirect());
    }
}
