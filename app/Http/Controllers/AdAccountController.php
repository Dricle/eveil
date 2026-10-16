<?php

namespace App\Http\Controllers;

use App\Enums\AdStatus;
use App\Http\Requests\AdAccountRequest;
use App\Jobs\SyncAdAccount;
use App\Models\AdAccount;
use App\Services\Ads\AdProposals;
use Illuminate\Http\RedirectResponse;

/**
 * A connected ad account's budget, and disconnecting it.
 */
class AdAccountController extends Controller
{
    public function update(AdAccountRequest $request, int $adAccount): RedirectResponse
    {
        $account = AdAccount::query()->findOrFail($adAccount);

        $account->update(['monthly_budget_micros' => AdProposals::micros((float) $request->validated('monthly_budget'))]);

        // A budget lowered under what was spent pauses at once, not at the
        // next hourly sync.
        SyncAdAccount::dispatch($account);

        return back()->with('status', 'Monthly budget saved.');
    }

    /**
     * Refused while campaigns still run there: Eveil would stop watching
     * them, and they would keep spending with nobody minding the budget.
     */
    public function destroy(int $adAccount): RedirectResponse
    {
        $account = AdAccount::query()->findOrFail($adAccount);

        if ($account->campaigns()->where('status', AdStatus::Active)->exists()) {
            return back()->withErrors(['account' => 'Campaigns still run on this account. Set its budget to zero first: that pauses them.']);
        }

        $account->delete();

        return back()->with('status', 'Ad account disconnected.');
    }
}
