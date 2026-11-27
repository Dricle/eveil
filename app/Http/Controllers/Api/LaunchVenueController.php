<?php

namespace App\Http\Controllers\Api;

use App\Actions\AddLaunchVenue;
use App\Actions\RefreshLaunchList;
use App\Http\Controllers\Controller;
use App\Http\Requests\LaunchItemRequest;
use App\Http\Requests\LaunchVenueRequest;
use App\Http\Resources\LaunchItemResource;
use App\Models\LaunchItem;
use App\Models\LaunchVenue;
use App\Support\CurrentProject;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * The launch checklist: one row per venue, with its state, copy and posts.
 * Writing copy runs a model, so it stays in the app and with Evie; this is
 * the checklist itself, which follows the project's launch tags.
 */
class LaunchVenueController extends Controller
{
    public function index(CurrentProject $currentProject, RefreshLaunchList $refresh): AnonymousResourceCollection
    {
        $refresh->handle($currentProject->getOrFail());

        return LaunchItemResource::collection(LaunchItem::query()
            ->whereIn('launch_venue_id', LaunchVenue::query()->select('id'))
            ->with(['venue', 'submissions', 'latestSubmission', 'agentRun'])
            ->oldest('id')
            ->paginate(100));
    }

    public function store(LaunchVenueRequest $request, AddLaunchVenue $add, CurrentProject $currentProject): LaunchItemResource
    {
        $item = $add->handle($currentProject->getOrFail(), $request->validated());

        return LaunchItemResource::make($item->load(['venue', 'submissions', 'latestSubmission', 'agentRun']));
    }

    public function update(LaunchItemRequest $request, int $launchVenue): LaunchItemResource
    {
        $item = LaunchItem::query()->findOrFail($launchVenue);

        $item->update([
            ...$request->has('dismissed') ? ['dismissed_at' => $request->boolean('dismissed') ? now() : null] : [],
            ...$request->has('draft') ? ['draft' => $request->validated('draft')] : [],
        ]);

        return LaunchItemResource::make($item->load(['venue', 'submissions', 'latestSubmission', 'agentRun']));
    }
}
