<?php

namespace App\Cloud\Actions;

use App\Models\Organization;
use App\Support\Settings;
use Laravel\Cashier\Checkout;

/**
 * Pay-as-you-go: whatever amount the customer chooses, converted to credits
 * at the flat, published rate - never a pre-created Stripe price, so there
 * is nothing to configure in Stripe before this works. The credit count is
 * locked into the session's `metadata` at THIS moment, not recomputed by the
 * webhook later: what the customer saw on the button is what they get, even
 * if an operator changes the rate before Stripe confirms the payment.
 */
class StartCheckout
{
    public function __construct(private Settings $settings) {}

    public function handle(Organization $organization, int $amountCents): Checkout
    {
        $credits = intdiv($amountCents * $this->settings->int('billing.credits_per_dollar'), 100);

        // Same ad-hoc product/price `checkoutCharge()` builds, called
        // through the builder directly rather than that shortcut: it has no
        // way to chain `collectTaxIds()`, which adds Stripe's own "I'm
        // purchasing as a business" checkbox - VAT number and billing
        // address, collected and stored on the Stripe customer, never by us.
        return Checkout::customer($organization, $organization)
            ->collectTaxIds()
            ->create([[
                'price_data' => [
                    'currency' => $organization->preferredCurrency(),
                    'product_data' => ['name' => 'Credits top-up'],
                    'unit_amount_decimal' => $amountCents,
                ],
                'quantity' => 1,
            ]], [
                'success_url' => route('settings.organization.billing.edit', ['checkout' => 'success']),
                'cancel_url' => route('settings.organization.billing.edit'),
                'metadata' => ['credits' => $credits],
                // Cashier sets `customer_update.name` for tax ID collection
                // but not `address`: Stripe refuses to collect a tax ID for
                // a customer it holds no address for, and this Organization
                // never had one entered anywhere. `auto` lets Stripe write
                // back the address the customer types on its own page.
                'customer_update' => ['address' => 'auto'],
                // A one-time Checkout payment produces a Charge, not an
                // Invoice, unless asked: without this, the Billing Portal's
                // invoice history has nothing to list for a top-up.
                'invoice_creation' => ['enabled' => true],
            ]);
    }
}
