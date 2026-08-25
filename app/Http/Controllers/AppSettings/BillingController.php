<?php

namespace App\Http\Controllers\AppSettings;

use App\Http\Controllers\Controller;
use App\Http\Requests\AppSettings\BillingRequest;
use App\Support\Settings;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Cloud-only, and the route stays reachable even so: self-hosted never links
 * here (`AppSettingsLayout` hides the tab off the shared `edition` prop), and
 * a superadmin who lands on the URL directly still sees the trial and rate
 * configuration rather than a 404 — `.ai/rules/controllers.md`'s "404 for
 * access, not a feature that doesn't apply here".
 */
class BillingController extends Controller
{
    public function __construct(private Settings $settings) {}

    public function edit(): Response
    {
        return Inertia::render('app-settings/Billing', [
            'billing' => [
                'trial_credits' => $this->settings->int('billing.trial_credits'),
                'trial_lead_limit' => $this->settings->int('billing.trial_lead_limit'),
                'credits_per_dollar' => $this->settings->int('billing.credits_per_dollar'),
            ],
        ]);
    }

    public function update(BillingRequest $request): RedirectResponse
    {
        $values = $request->validated();

        $this->settings->set('billing.trial_credits', $values['trial_credits']);
        $this->settings->set('billing.trial_lead_limit', $values['trial_lead_limit']);
        $this->settings->set('billing.credits_per_dollar', $values['credits_per_dollar']);

        return to_route('app-settings.billing.edit')->with('status', 'Billing saved.');
    }
}
