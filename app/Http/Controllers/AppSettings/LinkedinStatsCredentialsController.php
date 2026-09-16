<?php

namespace App\Http\Controllers\AppSettings;

use App\Http\Controllers\Controller;
use App\Http\Requests\AppSettings\LinkedinStatsCredentialsRequest;
use App\Support\LinkedinCredentials;
use Illuminate\Http\RedirectResponse;

/**
 * The SECOND, optional LinkedIn Developer App - Community Management API,
 * for post-performance polling. LinkedIn does not allow that product to
 * live on the same app as Share on LinkedIn, so this is a genuinely
 * separate registration. The screen showing the current value is owned by
 * `LinkedinCredentialsController::edit()`, same split as the primary app's
 * own update/destroy against that same screen.
 */
class LinkedinStatsCredentialsController extends Controller
{
    public function update(LinkedinStatsCredentialsRequest $request, LinkedinCredentials $credentials): RedirectResponse
    {
        $credentials->saveStats($request->string('stats_client_id')->value(), $request->string('stats_client_secret')->value());

        return to_route('app-settings.linkedin.edit')->with('status', 'Community Management app credentials saved.');
    }

    public function destroy(LinkedinCredentials $credentials): RedirectResponse
    {
        $credentials->forgetStats();

        return to_route('app-settings.linkedin.edit')->with('status', 'Community Management app credentials removed.');
    }
}
