<?php

namespace App\Http\Controllers;

use App\Enums\SocialPlatform;
use App\Http\Requests\SocialPostFrequencyRequest;
use App\Support\CurrentProject;
use Illuminate\Http\RedirectResponse;

/**
 * How often the current project wants a new post on one network, set from
 * that network's queue. A cadence that changed makes the network due now:
 * nothing else ever sets its next date, so a cadence just turned on would
 * otherwise never be picked up. An unchanged one keeps its date, so saving
 * does not draft again.
 */
class SocialCadenceController extends Controller
{
    public function update(SocialPostFrequencyRequest $request, CurrentProject $currentProject, SocialPlatform $platform): RedirectResponse
    {
        $project = $currentProject->getOrFail();
        $project->setAttribute($platform->frequencyColumn(), $request->validated('frequency'));

        if ($project->isDirty($platform->frequencyColumn())) {
            $project->setAttribute($platform->nextPostColumn(), now());
        }

        $project->save();

        return to_route('social.posts.index', $platform);
    }
}
