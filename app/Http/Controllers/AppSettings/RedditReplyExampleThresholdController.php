<?php

namespace App\Http\Controllers\AppSettings;

use App\Http\Controllers\Controller;
use App\Http\Requests\AppSettings\RedditReplyExampleThresholdRequest;
use App\Support\Settings;
use Illuminate\Http\RedirectResponse;

/**
 * The quality bar itself. The screen showing the current value is owned by
 * `RedditReplyExampleController::index()`. Copy of `LinkedinExampleThresholdController`.
 */
class RedditReplyExampleThresholdController extends Controller
{
    public function __construct(private Settings $settings) {}

    public function update(RedditReplyExampleThresholdRequest $request): RedirectResponse
    {
        $this->settings->set('reddit_examples.min_score', $request->validated('min_score'));

        return to_route('app-settings.reddit-reply-examples.index')->with('status', 'Threshold saved.');
    }
}
