<?php

namespace App\Http\Controllers;

use App\Enums\SocialPlatform;
use App\Http\Requests\SocialPostFrequencyRequest;
use App\Support\CurrentProject;
use Illuminate\Http\RedirectResponse;

/**
 * How often the current project wants a new X and a new Bluesky post. A
 * network whose cadence just changed is due now: nothing else ever sets its
 * next date, so a cadence just turned on would otherwise never be picked up.
 * An unchanged one keeps its date, so saving does not draft again.
 */
class SocialCadenceController extends Controller
{
    public function update(SocialPostFrequencyRequest $request, CurrentProject $currentProject): RedirectResponse
    {
        $project = $currentProject->getOrFail();
        $project->fill($request->validated());

        foreach (SocialPlatform::cases() as $platform) {
            if ($project->isDirty($platform->frequencyColumn())) {
                $project->setAttribute($platform->nextPostColumn(), now());
            }
        }

        $project->save();

        return to_route('social.posts.index');
    }
}
