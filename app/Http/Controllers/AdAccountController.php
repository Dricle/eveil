<?php

namespace App\Http\Controllers;

use App\Enums\AdStatus;
use App\Http\Requests\AdAccountRequest;
use App\Jobs\SyncAdAccount;
use App\Models\AdAccount;
use Illuminate\Http\RedirectResponse;

/**
 * Whether Eveil may spend the project's ads budget on a connected account,
 * who its ads are posted as, and disconnecting it.
 */
class AdAccountController extends Controller
{
    public function update(AdAccountRequest $request, int $adAccount): RedirectResponse
    {
        $account = AdAccount::query()->findOrFail($adAccount);

        if ($request->has('identity_id')) {
            $account->update(['identity_id' => $request->string('identity_id')->value()]);

            return back()->with('status', 'Ads on this account will be posted as '.collect($account->identities)->firstWhere('id', $account->identity_id)['name'].'.');
        }

        if ($request->boolean('enabled') && $account->needsIdentity()) {
            return back()->withErrors(['account' => "Pick the {$account->platform->client()->capabilities()->identity} these ads are posted as first."]);
        }

        $account->update(['enabled' => $request->boolean('enabled')]);

        // Switched off, its campaigns pause at once, not at the next hourly
        // sync.
        SyncAdAccount::dispatch($account);

        return back()->with('status', $account->enabled ? 'Eveil may now spend on this account.' : 'Eveil no longer spends on this account.');
    }

    /**
     * Refused while campaigns still run there: Eveil would stop watching
     * them, and they would keep spending with nobody minding the budget.
     */
    public function destroy(int $adAccount): RedirectResponse
    {
        $account = AdAccount::query()->findOrFail($adAccount);

        if ($account->campaigns()->where('status', AdStatus::Active)->exists()) {
            return back()->withErrors(['account' => 'Campaigns still run on this account. Switch it off first: that pauses them.']);
        }

        $account->delete();

        return back()->with('status', 'Ad account disconnected.');
    }
}
