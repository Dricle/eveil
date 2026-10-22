<?php

namespace App\Http\Controllers\AppSettings;

use App\Http\Controllers\Controller;
use App\Http\Requests\AppSettings\RedditReplyExampleHarvestRequest;
use App\Support\Settings;
use Illuminate\Http\RedirectResponse;

/**
 * Whether a scan keeps other people's high-scoring product mentions, and the
 * score they have to clear. Separate from `RedditReplyExampleThresholdController`
 * because the two bars govern different things: that one is our own reply
 * earning its place, this one is a stranger's comment being kept at all. The
 * screen showing both is owned by `RedditReplyExampleController::index()`.
 */
class RedditReplyExampleHarvestController extends Controller
{
    public function __construct(private Settings $settings) {}

    public function update(RedditReplyExampleHarvestRequest $request): RedirectResponse
    {
        $this->settings->set('reddit_examples.harvest_enabled', $request->validated('enabled'));
        $this->settings->set('reddit_examples.harvest_min_score', $request->validated('min_score'));

        return to_route('app-settings.reddit-reply-examples.index')->with('status', 'Harvest settings saved.');
    }
}
