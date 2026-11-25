<?php

use App\Enums\SocialAccountStatus;
use App\Enums\SocialPlatform;
use App\Models\Organization;
use App\Models\PlatformApp;
use App\Models\Project;
use App\Models\SocialAccount;
use App\Models\User;
use App\Support\CurrentProject;
use App\Support\LinkedinCredentials;
use App\Support\Settings;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    app(LinkedinCredentials::class)->save('client-id', 'client-secret');
});

function linkedinOauthUser(): array
{
    $user = User::factory()->create();
    $organization = Organization::factory()->create();
    $organization->users()->attach($user, ['role' => 'owner']);
    $project = Project::factory()->for($organization)->create();

    app(CurrentProject::class)->set($project);

    return [$user, $project, $organization];
}

it('connects a LinkedIn account on a valid callback', function () {
    [$user, $project, $organization] = linkedinOauthUser();

    Http::fake([
        'https://www.linkedin.com/oauth/v2/accessToken' => Http::response([
            'access_token' => 'at-1',
            'refresh_token' => 'rt-1',
            'expires_in' => 5184000,
            'refresh_token_expires_in' => 31536000,
        ]),
        'https://api.linkedin.com/v2/userinfo' => Http::response([
            'sub' => 'abc123',
            'name' => 'Clement Rigo',
        ]),
    ]);

    $this->actingAs($user)
        ->withSession(['linkedin_oauth_state' => 'the-state', 'linkedin_oauth_project_id' => $project->id])
        ->get(route('oauth.linkedin.callback', ['code' => 'a-code', 'state' => 'the-state']))
        ->assertRedirect(route('settings.linkedin.index', $project));

    $account = SocialAccount::query()->where('organization_id', $organization->id)->sole();

    expect($account->platform)->toBe(SocialPlatform::Linkedin)
        ->and($account->external_id)->toBe('urn:li:person:abc123')
        ->and($account->display_name)->toBe('Clement Rigo')
        ->and($account->secret)->toBe('at-1')
        ->and($account->refresh_secret)->toBe('rt-1')
        ->and($account->status)->toBe(SocialAccountStatus::Active);
});

it('refuses a callback whose state does not match', function () {
    [$user, $project] = linkedinOauthUser();

    Http::fake();

    $this->actingAs($user)
        ->withSession(['linkedin_oauth_state' => 'expected', 'linkedin_oauth_project_id' => $project->id])
        ->get(route('oauth.linkedin.callback', ['code' => 'a-code', 'state' => 'tampered']))
        ->assertRedirect(route('settings.linkedin.index', $project));

    expect(SocialAccount::count())->toBe(0);
    Http::assertNothingSent();
});

it('sends a callback with no project in session to the app home', function () {
    [$user] = linkedinOauthUser();

    Http::fake();

    $this->actingAs($user)->withSession(['linkedin_oauth_state' => 'the-state'])
        ->get(route('oauth.linkedin.callback', ['code' => 'a-code', 'state' => 'the-state']))
        ->assertRedirect(route('app.home'));

    expect(SocialAccount::count())->toBe(0);
    Http::assertNothingSent();
});

it('moves the LinkedIn apps out of settings onto instance platform apps', function () {
    PlatformApp::query()->delete();
    $settings = app(Settings::class);
    $settings->set('linkedin.client_id', 'posting-id');
    $settings->set('linkedin.client_secret', 'posting-secret', encrypted: true);
    $settings->set('linkedin.stats_client_id', 'stats-id');
    $settings->set('linkedin.stats_client_secret', 'stats-secret', encrypted: true);

    (require database_path('migrations/2026_11_25_173024_move_linkedin_apps_to_platform_apps.php'))->up();
    $settings->flush();

    $credentials = app(LinkedinCredentials::class);
    expect($credentials->clientId())->toBe('posting-id')
        ->and($credentials->clientSecret())->toBe('posting-secret')
        ->and($credentials->statsClientId())->toBe('stats-id')
        ->and($credentials->statsClientSecret())->toBe('stats-secret')
        ->and(PlatformApp::query()->whereNotNull('organization_id')->exists())->toBeFalse()
        ->and($settings->get('linkedin.client_id'))->toBeNull()
        ->and($settings->hasSecret('linkedin.stats_client_secret'))->toBeFalse();
});
