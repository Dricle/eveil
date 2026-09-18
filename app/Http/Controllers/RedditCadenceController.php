<?php

namespace App\Http\Controllers;

use App\Http\Requests\RedditScanFrequencyRequest;
use App\Support\CurrentProject;
use Illuminate\Http\RedirectResponse;

/**
 * How often the current project wants a Reddit opportunity scan. Lives on
 * the replies queue screen, not a separate settings screen - same shape as
 * `LinkedinCadenceController`, one difference: `reddit_next_scan_at` is
 * always reset to now on save. Unlike the LinkedIn cadence, nothing else in
 * this feature ever sets that column, so leaving it untouched would mean a
 * project that just turned the cadence on is never picked up by
 * `eveil:reddit-scan-due` at all.
 */
class RedditCadenceController extends Controller
{
    public function update(RedditScanFrequencyRequest $request, CurrentProject $currentProject): RedirectResponse
    {
        $currentProject->getOrFail()->update([
            ...$request->validated(),
            'reddit_next_scan_at' => now(),
        ]);

        return to_route('reddit.replies.index');
    }
}
