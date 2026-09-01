<?php

namespace App\Http\Controllers;

use App\Actions\SubmitDiscoveryLinks;
use App\Http\Requests\SubmitDiscoveryLinksRequest;
use App\Models\TargetProfile;
use Illuminate\Http\RedirectResponse;

/**
 * A lead somebody already had: a company site, a directory page, pasted in
 * rather than found by an agent. Routed into the same graph a search run
 * uses, so it lands on the same run screen and in the same company table.
 *
 * No validation rule on the profile id: the global scope already answers
 * whether it belongs to this project, and anything else is a 404 -- same
 * reasoning as `DiscoveryRunController::store`.
 */
class DiscoveryLinkController extends Controller
{
    public function store(SubmitDiscoveryLinksRequest $request, SubmitDiscoveryLinks $submit): RedirectResponse
    {
        $targetProfile = TargetProfile::query()->findOrFail($request->integer('target_profile'));

        $run = $submit->handle($targetProfile, $request->input('links'));

        return to_route('discovery-runs.show', $run);
    }
}
