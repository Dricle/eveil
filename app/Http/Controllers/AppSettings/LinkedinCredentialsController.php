<?php

namespace App\Http\Controllers\AppSettings;

use App\Http\Controllers\Controller;
use App\Http\Requests\AppSettings\LinkedinCredentialsRequest;
use App\Support\LinkedinCredentials;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The instance's own LinkedIn Developer App. Self-hosted needs its own app
 * plus `w_member_social`/OIDC product approval; the secret never travels
 * back to the browser, same as the AI provider key screen.
 */
class LinkedinCredentialsController extends Controller
{
    public function edit(LinkedinCredentials $credentials): Response
    {
        return Inertia::render('app-settings/Linkedin', [
            'clientId' => $credentials->clientId(),
            'configured' => $credentials->isConfigured(),
            // The second, optional app for post-performance polling - see
            // `LinkedinStatsCredentialsController`.
            'statsClientId' => $credentials->statsClientId(),
            'statsConfigured' => $credentials->isStatsConfigured(),
        ]);
    }

    public function update(LinkedinCredentialsRequest $request, LinkedinCredentials $credentials): RedirectResponse
    {
        $credentials->save($request->string('client_id')->value(), $request->string('client_secret')->value());

        return to_route('app-settings.linkedin.edit')->with('status', 'LinkedIn app credentials saved.');
    }

    public function destroy(LinkedinCredentials $credentials): RedirectResponse
    {
        $credentials->forget();

        return to_route('app-settings.linkedin.edit')->with('status', 'LinkedIn app credentials removed.');
    }
}
