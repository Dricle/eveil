<?php

use App\Enums\LinkedinAccountStatus;
use App\Models\LinkedinAccount;
use App\Models\Organization;
use App\Models\Project;
use App\Models\User;
use App\Support\CurrentProject;
use App\Support\LinkedinCredentials;
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
    [$user, , $organization] = linkedinOauthUser();

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

    $this->actingAs($user)->withSession(['linkedin_oauth_state' => 'the-state'])
        ->get(route('linkedin.oauth.callback', ['code' => 'a-code', 'state' => 'the-state']))
        ->assertRedirect(route('linkedin.account.index'));

    $account = LinkedinAccount::query()->where('organization_id', $organization->id)->sole();

    expect($account->member_urn)->toBe('urn:li:person:abc123')
        ->and($account->display_name)->toBe('Clement Rigo')
        ->and($account->access_token)->toBe('at-1')
        ->and($account->status)->toBe(LinkedinAccountStatus::Active);
});

it('refuses a callback whose state does not match', function () {
    [$user] = linkedinOauthUser();

    Http::fake();

    $this->actingAs($user)->withSession(['linkedin_oauth_state' => 'expected'])
        ->get(route('linkedin.oauth.callback', ['code' => 'a-code', 'state' => 'tampered']))
        ->assertRedirect(route('linkedin.account.index'));

    expect(LinkedinAccount::count())->toBe(0);
    Http::assertNothingSent();
});
