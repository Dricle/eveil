<?php

namespace App\Http\Controllers;

use App\Actions\EnforceAdBudget;
use App\Http\Requests\AdBudgetRequest;
use App\Services\Ads\AdProposals;
use App\Support\CurrentProject;
use Illuminate\Http\RedirectResponse;

/**
 * The project's monthly ads budget: one amount for every network, which the
 * agent spreads over the accounts switched on.
 */
class AdBudgetController extends Controller
{
    public function update(AdBudgetRequest $request, CurrentProject $currentProject, EnforceAdBudget $enforce): RedirectResponse
    {
        $project = $currentProject->getOrFail();

        $project->update([
            'ads_monthly_budget_micros' => AdProposals::micros((float) $request->validated('monthly_budget')),
            'ads_currency' => $request->validated('currency'),
        ]);

        // A budget lowered under what was spent, or moved to a currency some
        // accounts are not billed in, pauses at once, not at the next sync.
        $enforce->handle($project);

        return back()->with('status', 'Monthly ads budget saved.');
    }
}
