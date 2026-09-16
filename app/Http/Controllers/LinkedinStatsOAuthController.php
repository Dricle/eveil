<?php

namespace App\Http\Controllers;

use App\Models\LinkedinAccount;
use App\Support\LinkedinCredentials;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

/**
 * A SECOND OAuth connection, to a SECOND LinkedIn Developer App: the
 * Community Management API's restricted `r_member_social_feed`
 * (post-performance polling) cannot live on the same app as Share on
 * LinkedIn - LinkedIn does not allow it, confirmed by trying. So this
 * attaches a second token to an already-connected `LinkedinAccount` rather
 * than widening `LinkedinOAuthController`'s scope.
 *
 * No fresh identity fetch on callback: it is the same member reconnecting
 * under a second app registration, and the account it belongs to already
 * travelled through in `state`.
 */
class LinkedinStatsOAuthController extends Controller
{
    public function redirect(Request $request, int $linkedinAccount, LinkedinCredentials $credentials): RedirectResponse
    {
        $account = LinkedinAccount::query()->ownedBy($request->user())->findOrFail($linkedinAccount);

        $state = Str::random(40);
        $request->session()->put('linkedin_stats_oauth_state', $state);
        $request->session()->put('linkedin_stats_oauth_account_id', $account->id);

        $query = http_build_query([
            'response_type' => 'code',
            'client_id' => $credentials->statsClientId(),
            'redirect_uri' => route('linkedin.stats.oauth.callback'),
            'scope' => 'r_member_social_feed',
            'state' => $state,
        ]);

        return redirect("https://www.linkedin.com/oauth/v2/authorization?{$query}");
    }

    public function callback(Request $request, LinkedinCredentials $credentials): RedirectResponse
    {
        $state = $request->session()->pull('linkedin_stats_oauth_state');
        $accountId = $request->session()->pull('linkedin_stats_oauth_account_id');

        if ($state === null || ! hash_equals($state, (string) $request->query('state')) || $accountId === null) {
            return to_route('settings.linkedin.index')->with('status', 'LinkedIn connection failed: the request could not be verified.');
        }

        $account = LinkedinAccount::query()->find((int) $accountId);

        if ($account === null) {
            return to_route('settings.linkedin.index')->with('status', 'That LinkedIn account no longer exists.');
        }

        $token = Http::asForm()->post('https://www.linkedin.com/oauth/v2/accessToken', [
            'grant_type' => 'authorization_code',
            'code' => (string) $request->query('code'),
            'redirect_uri' => route('linkedin.stats.oauth.callback'),
            'client_id' => $credentials->statsClientId(),
            'client_secret' => $credentials->statsClientSecret(),
        ]);

        if (! $token->successful()) {
            return to_route('settings.linkedin.index')->with('status', 'LinkedIn did not grant performance-polling access. LinkedIn grants r_member_social_feed selectively - this may simply be refused.');
        }

        $account->update([
            'stats_access_token' => (string) $token->json('access_token'),
            'stats_refresh_token' => $token->json('refresh_token'),
            'stats_access_token_expires_at' => now()->addSeconds((int) $token->json('expires_in')),
            'stats_refresh_token_expires_at' => $token->json('refresh_token_expires_in') !== null
                ? now()->addSeconds((int) $token->json('refresh_token_expires_in'))
                : null,
        ]);

        return to_route('settings.linkedin.index')->with('status', 'Performance polling connected.');
    }
}
