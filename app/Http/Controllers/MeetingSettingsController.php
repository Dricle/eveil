<?php

namespace App\Http\Controllers;

use App\Http\Requests\MeetingSettingsRequest;
use App\Support\CurrentProject;
use DateTimeZone;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

/**
 * When the user takes calls, set once: the weekly hours, how long a call
 * lasts, the zone those hours are meant in, and where it happens. An answer
 * offering times picks from this and from nothing else. No calendar is
 * connected, so these hours are the whole truth about when somebody is free.
 */
class MeetingSettingsController extends Controller
{
    public function edit(CurrentProject $currentProject): Response
    {
        $project = $currentProject->getOrFail();

        return Inertia::render('settings/Meetings', [
            'durationMinutes' => $project->meeting_duration_minutes,
            'timezone' => $project->meeting_timezone,
            'hours' => $project->meeting_hours ?? [],
            'location' => $project->meeting_location,
            'timezones' => DateTimeZone::listIdentifiers(),
        ]);
    }

    public function update(MeetingSettingsRequest $request, CurrentProject $currentProject): RedirectResponse
    {
        $validated = $request->validated();

        $currentProject->getOrFail()->update([
            'meeting_duration_minutes' => $validated['meeting_duration_minutes'],
            'meeting_timezone' => $validated['meeting_timezone'],
            'meeting_hours' => array_values($validated['meeting_hours'] ?? []),
            'meeting_location' => $validated['meeting_location'] ?? null,
        ]);

        return to_route('settings.meetings.edit');
    }
}
