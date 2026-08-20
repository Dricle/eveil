<?php

namespace App\Http\Controllers;

use App\Enums\TargetProfileSource;
use App\Http\Requests\TargetProfileRequest;
use App\Http\Resources\TargetProfileResource;
use App\Models\TargetProfile;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Who the search goes after. The agent writes these from the product portrait;
 * this is where the person who knows the market corrects them.
 *
 * A profile is a place rather than a row in a list: it has its own page and its
 * own searches, and the section's navigation is the profiles themselves. So
 * there is no index page: landing on the section lands on a profile.
 *
 * Ids are looked up here rather than type-hinted into the action: route model
 * binding resolves in the `web` group, before the middleware that sets the
 * current project, so a bound model is fetched while the scope is still inert
 * and any id in the table would resolve.
 */
class TargetProfileController extends Controller
{
    public function index(): Response|RedirectResponse
    {
        $first = TargetProfile::query()->orderBy('id')->first();

        return $first === null
            ? Inertia::render('targets/Empty')
            : to_route('targets.show', $first);
    }

    public function create(): Response
    {
        return Inertia::render('targets/Profile', ['profile' => null]);
    }

    public function store(TargetProfileRequest $request): RedirectResponse
    {
        $profile = TargetProfile::create([
            ...$request->columns(),
            'criteria' => $request->criteria(),
            'source' => TargetProfileSource::Human,
        ]);

        return to_route('targets.show', $profile);
    }

    public function show(int $target): Response
    {
        return Inertia::render('targets/Profile', [
            'profile' => TargetProfileResource::make(TargetProfile::query()->findOrFail($target)),
        ]);
    }

    public function update(TargetProfileRequest $request, int $target): RedirectResponse
    {
        $profile = TargetProfile::query()->findOrFail($target);

        $profile->update([
            ...$request->columns(),

            // Merged rather than replaced: `confidence` is the model's report
            // on its own run, and the person fixing a sector list is not
            // restating it.
            'criteria' => [...$profile->criteria, ...$request->criteria()],

            // A corrected profile is the user's from now on, which is what
            // keeps the next derivation from throwing it away.
            'source' => TargetProfileSource::Human,
        ]);

        return to_route('targets.show', $profile);
    }

    public function destroy(int $target): RedirectResponse
    {
        TargetProfile::query()->findOrFail($target)->delete();

        return to_route('targets.index');
    }
}
