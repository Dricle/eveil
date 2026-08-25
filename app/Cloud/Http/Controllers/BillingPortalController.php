<?php

namespace App\Cloud\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Support\CurrentProject;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response;

/**
 * Invoice history and download, entirely Stripe-hosted: the Billing Portal
 * lists every invoice a customer has (including one-time top-ups, since
 * `StartCheckout` turns each into a real Invoice via `invoice_creation`) and
 * serves the PDFs itself. Nothing here to list, store or render.
 */
class BillingPortalController extends Controller
{
    public function __construct(private CurrentProject $currentProject) {}

    public function create(): Response
    {
        $organization = $this->currentProject->organization();

        // Same reasoning as `CheckoutController`: the link is marked
        // `external` in the template, but wrap it anyway in case an Inertia
        // visit ever reaches this route regardless.
        return Inertia::location(
            $organization->redirectToBillingPortal(route('settings.organization.billing.edit'))
        );
    }
}
