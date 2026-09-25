<?php

namespace App\Http\Controllers\AppSettings;

use App\Http\Controllers\Controller;
use App\Http\Requests\AppSettings\SocialExampleThresholdRequest;
use App\Support\Settings;
use Illuminate\Http\RedirectResponse;

/**
 * The Bluesky bank's like-count bar. The screen showing it is owned by
 * `SocialPostExampleController::index()`, same split as LinkedIn's.
 */
class SocialExampleThresholdController extends Controller
{
    public function __construct(private Settings $settings) {}

    public function update(SocialExampleThresholdRequest $request): RedirectResponse
    {
        $this->settings->set('social_examples.min_likes', $request->validated('min_likes'));

        return to_route('app-settings.social-post-examples.index')->with('status', 'Threshold saved.');
    }
}
