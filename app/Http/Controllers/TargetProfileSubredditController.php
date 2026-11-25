<?php

namespace App\Http\Controllers;

use App\Actions\EditTargetProfileSubreddits;
use App\Http\Requests\TargetProfileSubredditRequest;
use App\Models\TargetProfile;
use Illuminate\Http\RedirectResponse;

/**
 * One subreddit added to or removed from a profile by hand, from its page.
 * `TargetProfileSubredditsController` is the automatic lookup beside it.
 */
class TargetProfileSubredditController extends Controller
{
    public function store(TargetProfileSubredditRequest $request, EditTargetProfileSubreddits $edit, int $target): RedirectResponse
    {
        $refused = $edit->handle(TargetProfile::query()->findOrFail($target), add: [$request->validated('name')]);

        if ($refused !== []) {
            return back()->withErrors(['name' => 'No such subreddit on Reddit, or under 1000 subscribers.']);
        }

        return back();
    }

    public function destroy(EditTargetProfileSubreddits $edit, int $target, string $subreddit): RedirectResponse
    {
        $edit->handle(TargetProfile::query()->findOrFail($target), remove: [$subreddit]);

        return back();
    }
}
