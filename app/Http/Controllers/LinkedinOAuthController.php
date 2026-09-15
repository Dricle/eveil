<?php

namespace App\Http\Controllers;

use App\Enums\LinkedinAccountStatus;
use App\Support\CurrentProject;
use App\Support\LinkedinCredentials;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

/**
 * Connects the organization's LinkedIn member profile via OAuth
 * (`w_member_social`). No Socialite: a two-endpoint authorize/token flow does
 * not earn a new dependency, and `symfony/http-client` (already required by
 * `laravel/ai`) covers it through Laravel's `Http` facade.
 *
 * Grants to specific projects are a separate step on the LinkedIn account
 * screen (`LinkedinAccountController`), same as a mailbox: the account
 * connects first, which projects may use it is chosen afterwards.
 */
class LinkedinOAuthController extends Controller
{
    public function redirect(Request $request, CurrentProject $currentProject, LinkedinCredentials $credentials): RedirectResponse
    {
        $state = Str::random(40);
        $request->session()->put('linkedin_oauth_state', $state);

        $query = http_build_query([
            'response_type' => 'code',
            'client_id' => $credentials->clientId(),
            'redirect_uri' => route('linkedin.oauth.callback'),
            'scope' => 'openid profile w_member_social',
            'state' => $state,
        ]);

        return redirect("https://www.linkedin.com/oauth/v2/authorization?{$query}");
    }

    public function callback(Request $request, CurrentProject $currentProject, LinkedinCredentials $credentials): RedirectResponse
    {
        $state = $request->session()->pull('linkedin_oauth_state');

        if ($state === null || ! hash_equals($state, (string) $request->query('state'))) {
            return to_route('linkedin.account.index')->with('status', 'LinkedIn connection failed: the request could not be verified.');
        }

        $token = Http::asForm()->post('https://www.linkedin.com/oauth/v2/accessToken', [
            'grant_type' => 'authorization_code',
            'code' => (string) $request->query('code'),
            'redirect_uri' => route('linkedin.oauth.callback'),
            'client_id' => $credentials->clientId(),
            'client_secret' => $credentials->clientSecret(),
        ]);

        if (! $token->successful()) {
            return to_route('linkedin.account.index')->with('status', 'LinkedIn did not grant access. Try connecting again.');
        }

        $identity = Http::withToken((string) $token->json('access_token'))
            ->get('https://api.linkedin.com/v2/userinfo');

        if (! $identity->successful()) {
            return to_route('linkedin.account.index')->with('status', 'Connected, but LinkedIn did not return the profile.');
        }

        $organization = $currentProject->organization();

        $organization->linkedinAccounts()->updateOrCreate(
            ['member_urn' => 'urn:li:person:'.$identity->json('sub')],
            [
                'display_name' => (string) $identity->json('name'),
                'access_token' => (string) $token->json('access_token'),
                'refresh_token' => $token->json('refresh_token'),
                'access_token_expires_at' => now()->addSeconds((int) $token->json('expires_in')),
                'refresh_token_expires_at' => $token->json('refresh_token_expires_in') !== null
                    ? now()->addSeconds((int) $token->json('refresh_token_expires_in'))
                    : null,
                'status' => LinkedinAccountStatus::Active,
                'last_error' => null,
            ],
        );

        return to_route('linkedin.account.index')->with('status', 'LinkedIn account connected.');
    }
}
