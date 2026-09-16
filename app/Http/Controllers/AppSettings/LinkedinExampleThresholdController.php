<?php

namespace App\Http\Controllers\AppSettings;

use App\Http\Controllers\Controller;
use App\Http\Requests\AppSettings\LinkedinExampleThresholdRequest;
use App\Support\Settings;
use Illuminate\Http\RedirectResponse;

/**
 * The quality bar itself. The screen showing the current value is owned by
 * `LinkedinPostExampleController::index()`, same split as `EmailExampleThresholdController`
 * against the screen it saves back to.
 */
class LinkedinExampleThresholdController extends Controller
{
    public function __construct(private Settings $settings) {}

    public function update(LinkedinExampleThresholdRequest $request): RedirectResponse
    {
        $this->settings->set('linkedin_examples.min_likes', $request->validated('min_likes'));

        return to_route('app-settings.linkedin-post-examples.index')->with('status', 'Threshold saved.');
    }
}
