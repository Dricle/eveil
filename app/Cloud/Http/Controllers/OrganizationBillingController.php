<?php

namespace App\Cloud\Http\Controllers;

use App\Cloud\Http\Resources\CreditTransactionResource;
use App\Http\Controllers\Controller;
use App\Support\CurrentProject;
use App\Support\Settings;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class OrganizationBillingController extends Controller
{
    public function __construct(private CurrentProject $currentProject, private Settings $settings) {}

    public function edit(Request $request): Response
    {
        $organization = $this->currentProject->organization();

        return Inertia::render('settings/OrganizationBilling', [
            // Only ever a success/cancel echo from Stripe Checkout's redirect.
            'checkout' => $request->query('checkout'),
            'onTrial' => $organization->isOnTrial(),
            'balance' => $organization->credits_balance,
            'creditsPerDollar' => $this->settings->int('billing.credits_per_dollar'),
            'hasPaymentMethod' => $organization->hasDefaultPaymentMethod(),
            'autoTopup' => [
                'threshold' => $organization->auto_topup_threshold,
                'amountCents' => $organization->auto_topup_amount_cents,
            ],
            'transactions' => CreditTransactionResource::collection(
                $organization->creditTransactions()->latest('id')->limit(50)->get()
            ),
        ]);
    }
}
