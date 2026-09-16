<?php

use App\Models\LinkedinAccount;
use App\Models\Organization;
use App\Models\Project;
use App\Models\User;
use App\Support\CurrentProject;
use App\Support\LinkedinCredentials;
use Illuminate\Support\Facades\Http;

function statsOauthSetup(): array
{
    $user = User::factory()->create();
    $organization = Organization::factory()->create();
    $organization->users()->attach($user, ['role' => 'owner']);
    $project = Project::factory()->for($organization)->create();
    app(CurrentProject::class)->set($project);

    $account = LinkedinAccount::factory()->create(['organization_id' => $organization->id]);

    return [$user, $account];
}

it('redirects to LinkedIn with the stats app client id and scope', function () {
    [$user, $account] = statsOauthSetup();
    app(LinkedinCredentials::class)->saveStats('stats-client-id', 'stats-secret');

    $response = $this->actingAs($user)->get(route('settings.linkedin.stats.connect', $account));

    $response->assertRedirect();
    expect($response->headers->get('Location'))
        ->toContain('linkedin.com/oauth/v2/authorization')
        ->toContain('client_id=stats-client-id')
        ->toContain('scope=r_member_social_feed');
});

it('refuses to start the stats connection for another organization\'s account', function () {
    [$user] = statsOauthSetup();
    $theirs = LinkedinAccount::factory()->create();

    $this->actingAs($user)->get(route('settings.linkedin.stats.connect', $theirs))->assertNotFound();
});

it('attaches the stats tokens to the account on a verified callback', function () {
    [$user, $account] = statsOauthSetup();
    app(LinkedinCredentials::class)->saveStats('stats-client-id', 'stats-secret');
    Http::fake(['linkedin.com/oauth/v2/accessToken' => Http::response([
        'access_token' => 'stats-access-token',
        'refresh_token' => 'stats-refresh-token',
        'expires_in' => 3600,
        'refresh_token_expires_in' => 31536000,
    ])]);

    $this->actingAs($user)->get(route('settings.linkedin.stats.connect', $account));
    $state = session('linkedin_stats_oauth_state');

    $this->actingAs($user)
        ->get(route('linkedin.stats.oauth.callback', ['code' => 'abc', 'state' => $state]))
        ->assertRedirect(route('settings.linkedin.index'));

    expect($account->fresh()->stats_access_token)->toBe('stats-access-token')
        ->and($account->fresh()->hasStatsAccess())->toBeTrue();
});

it('refuses a callback whose state does not match', function () {
    [$user, $account] = statsOauthSetup();
    app(LinkedinCredentials::class)->saveStats('stats-client-id', 'stats-secret');

    $this->actingAs($user)->get(route('settings.linkedin.stats.connect', $account));

    $this->actingAs($user)
        ->get(route('linkedin.stats.oauth.callback', ['code' => 'abc', 'state' => 'wrong']))
        ->assertRedirect(route('settings.linkedin.index'));

    expect($account->fresh()->hasStatsAccess())->toBeFalse();
});
