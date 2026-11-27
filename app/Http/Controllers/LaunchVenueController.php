<?php

namespace App\Http\Controllers;

use App\Actions\AddLaunchVenue;
use App\Enums\LaunchVenueOrigin;
use App\Http\Requests\LaunchVenueRequest;
use App\Models\LaunchItem;
use App\Support\CurrentProject;
use Illuminate\Http\RedirectResponse;

/**
 * Venues the user adds to their own checklist. Removing one soft deletes
 * the venue and keeps its checklist row, so the posts made there survive;
 * the checklist simply stops listing it. A catalog venue is dismissed
 * instead, never removed.
 */
class LaunchVenueController extends Controller
{
    public function store(LaunchVenueRequest $request, AddLaunchVenue $add, CurrentProject $currentProject): RedirectResponse
    {
        $add->handle($currentProject->getOrFail(), $request->validated());

        return back();
    }

    public function destroy(int $launchItem): RedirectResponse
    {
        $item = LaunchItem::query()->findOrFail($launchItem);

        abort_unless($item->venue->origin === LaunchVenueOrigin::User, 404);

        $item->venue->delete();

        return back();
    }
}
