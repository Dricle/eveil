<?php

namespace App\Http\Controllers;

use App\Ai\Agents\TargetProfileDeriver;
use App\Enums\AgentRunStatus;
use App\Enums\TargetProfileSource;
use App\Http\Requests\TargetProfileRequest;
use App\Http\Resources\TargetProfileResource;
use App\Models\AgentRun;
use App\Models\TargetProfile;
use App\Support\CurrentProject;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Who the search goes after. The agent writes these from the product portrait;
 * this is where the person who knows the market corrects them.
 *
 * Nothing here carries a project: the profiles of the current project are the
 * only ones the global scope will return, which is also what makes a profile id
 * from another project a 404 rather than a leak.
 *
 * That is why the row is looked up here rather than type-hinted into the
 * action. Route model binding is resolved by the `web` group, which runs BEFORE
 * the route middleware that sets the current project — so a bound model is
 * fetched while the scope is still inert, and any id in the table resolves.
 */
class TargetProfileController extends Controller
{
    public function __construct(private CurrentProject $currentProject) {}

    public function index(): Response
    {
        $derivation = AgentRun::query()->latestFor(TargetProfileDeriver::slug())->first();

        return Inertia::render('TargetProfiles', [
            'profiles' => TargetProfileResource::collection(
                TargetProfile::query()->orderBy('id')->get()
            ),
            'analyzed' => $this->currentProject->getOrFail()->knowledge_base !== null,
            'deriving' => $derivation?->isInFlight() ?? false,
            'derivationError' => $derivation?->status === AgentRunStatus::Failed
                ? $derivation->error
                : null,
        ]);
    }

    public function store(TargetProfileRequest $request): RedirectResponse
    {
        TargetProfile::create([
            ...$request->columns(),
            'criteria' => $request->criteria(),
            'source' => TargetProfileSource::Human,
        ]);

        return to_route('target-profiles.index');
    }

    public function update(TargetProfileRequest $request, int $targetProfile): RedirectResponse
    {
        $profile = TargetProfile::query()->findOrFail($targetProfile);

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

        return to_route('target-profiles.index');
    }

    public function destroy(int $targetProfile): RedirectResponse
    {
        TargetProfile::query()->findOrFail($targetProfile)->delete();

        return to_route('target-profiles.index');
    }
}
