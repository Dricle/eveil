<?php

namespace App\Http\Controllers\AppSettings;

use App\Enums\SocialPlatform;
use App\Http\Controllers\Controller;
use App\Http\Requests\AppSettings\SocialExampleThresholdRequest;
use App\Support\Settings;
use Illuminate\Http\RedirectResponse;

/**
 * One network's like-count bar. The screen showing it is owned by
 * `SocialPostExampleController::index()`. X has none: its numbers are never
 * read, so a request for it is a 404.
 */
class SocialExampleThresholdController extends Controller
{
    public function __construct(private Settings $settings) {}

    public function update(SocialExampleThresholdRequest $request, SocialPlatform $platform): RedirectResponse
    {
        $setting = $platform->minLikesSetting() ?? abort(404);

        $this->settings->set($setting, $request->validated('min_likes'));

        return to_route('app-settings.social-post-examples.index')->with('status', 'Threshold saved.');
    }
}
