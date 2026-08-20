<?php

namespace App\Http\Controllers;

use App\Actions\RunDiscovery;
use App\Http\Resources\DiscoveryRunResource;
use App\Http\Resources\TargetProfileResource;
use App\Models\DiscoveryRun;
use App\Models\TargetProfile;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The searches this project has run, and what each one is doing.
 *
 * Ids are looked up here rather than type-hinted into the action: route model
 * binding resolves in the `web` group, before the middleware that sets the
 * current project, so a bound model is fetched while the scope is still inert.
 */
class DiscoveryRunController extends Controller
{
    /**
     * The searches one profile has been put through. Searches belong to the
     * profile that asked for them: a run means nothing without the criteria it
     * was given.
     */
    public function index(int $target): Response
    {
        return Inertia::render('targets/Searches', [
            'profile' => TargetProfileResource::make(TargetProfile::query()->findOrFail($target)),
            'runs' => DiscoveryRunResource::collection(
                DiscoveryRun::query()->where('target_profile_id', $target)->latest('id')->limit(50)->get()
            ),
        ]);
    }

    public function show(int $discoveryRun): Response
    {
        $run = DiscoveryRun::query()
            ->with(['targetProfile', 'tasks' => fn ($query) => $query->with('agentRun')->orderBy('id')])
            ->findOrFail($discoveryRun);

        return Inertia::render('discovery/Run', [
            'run' => DiscoveryRunResource::make($run),
        ]);
    }

    /**
     * No validation rule on the profile id: the global scope already answers
     * whether it belongs to this project, and anything else is a 404.
     */
    public function store(Request $request, RunDiscovery $discover): RedirectResponse
    {
        $targetProfile = TargetProfile::query()->findOrFail($request->integer('target_profile'));

        $run = $discover->handle($targetProfile);

        return to_route('discovery-runs.show', $run);
    }
}
