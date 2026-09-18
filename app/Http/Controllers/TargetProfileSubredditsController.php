<?php

namespace App\Http\Controllers;

use App\Actions\FindSubreddits;
use App\Models\TargetProfile;
use Illuminate\Http\RedirectResponse;

/**
 * Resolving `criteria.subreddits` by hand, for the two paths that never get
 * it for free: a profile built through the "New profile" form and one Evie
 * creates via `CreateTargetProfile` both skip `FindSubreddits` entirely -
 * only `DeriveTargetProfiles`'s batch flow calls it today. Mechanical and
 * no-AI (`FindSubreddits`/`SubredditFinder`), so unlike deriving profiles
 * themselves this runs synchronously rather than through a queued job: a few
 * seconds, not the ~69s a generative agent call takes.
 */
class TargetProfileSubredditsController extends Controller
{
    public function store(FindSubreddits $findSubreddits, int $target): RedirectResponse
    {
        $profile = TargetProfile::query()->findOrFail($target);

        $findSubreddits->handle($profile);

        return back();
    }
}
