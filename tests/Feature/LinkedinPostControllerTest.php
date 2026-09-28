<?php

use App\Enums\SocialPostSourceType;
use App\Enums\SocialPostStatus;
use App\Enums\SocialPostVariant;
use App\Models\Organization;
use App\Models\Project;
use App\Models\SocialAccount;
use App\Models\SocialPost;
use App\Models\User;
use App\Support\CurrentProject;
use Illuminate\Support\Facades\Http;

/**
 * LinkedIn's side of `SocialPostController`, and the client-win pairs every
 * network now drafts.
 */
function linkedinSetup(): array
{
    $user = User::factory()->create();
    $organization = Organization::factory()->create();
    $organization->users()->attach($user, ['role' => 'owner']);
    $project = Project::factory()->for($organization)->create();

    $account = SocialAccount::factory()->linkedin()->create(['organization_id' => $organization->id]);
    $account->projects()->attach($project);

    app(CurrentProject::class)->set($project);

    return [$user, $project, $account];
}

function clientWinPair(Project $project, string $platform = 'linkedin'): array
{
    return collect([SocialPostVariant::Named, SocialPostVariant::Anonymized])
        ->map(fn (SocialPostVariant $variant): SocialPost => SocialPost::factory()->create([
            'project_id' => $project->id,
            'platform' => $platform,
            'source_type' => SocialPostSourceType::ClientWon,
            'source_ref' => '1',
            'variant' => $variant,
        ]))
        ->all();
}

it('approves a draft, publishes it synchronously, and rejects its sibling with a reason', function () {
    [$user, $project, $account] = linkedinSetup();
    Http::fake(['api.linkedin.com/rest/posts' => Http::response('', 201, ['x-restli-id' => 'urn:li:share:1'])]);
    [$named, $anonymized] = clientWinPair($project);

    $this->actingAs($user)
        ->from(route('social.posts.index', 'linkedin'))->post(route('social.posts.approve', $anonymized), ['social_account_id' => $account->id])
        ->assertRedirect(route('social.posts.index', 'linkedin'));

    expect($anonymized->fresh())
        ->status->toBe(SocialPostStatus::Published)
        ->external_id->toBe('urn:li:share:1')
        ->social_account_id->toBe($account->id)
        ->and($named->fresh())
        ->status->toBe(SocialPostStatus::Rejected)
        ->rejection_reason->toBe('Superseded by the other variant.');
});

it('publishes to the chosen account, not just the first one attached', function () {
    [$user, $project] = linkedinSetup();
    $second = SocialAccount::factory()->linkedin()->create(['organization_id' => $project->organization_id]);
    $second->projects()->attach($project);
    Http::fake(['api.linkedin.com/rest/posts' => Http::response('', 201, ['x-restli-id' => 'urn:li:share:2'])]);

    $post = SocialPost::factory()->linkedin()->create(['project_id' => $project->id]);

    $this->actingAs($user)->from(route('social.posts.index', 'linkedin'))
        ->post(route('social.posts.approve', $post), ['social_account_id' => $second->id]);

    expect($post->fresh()->social_account_id)->toBe($second->id);
});

it('refuses to publish a LinkedIn draft through another network\'s account', function () {
    [$user, $project] = linkedinSetup();
    $bluesky = SocialAccount::factory()->create(['organization_id' => $project->organization_id]);
    $bluesky->projects()->attach($project);
    Http::fake();

    $post = SocialPost::factory()->linkedin()->create(['project_id' => $project->id]);

    $this->actingAs($user)->post(route('social.posts.approve', $post), ['social_account_id' => $bluesky->id])->assertNotFound();

    expect($post->fresh()->status)->toBe(SocialPostStatus::Draft);
    Http::assertNothingSent();
});

it('keeps a draft as draft with the error visible when publishing fails', function () {
    [$user, $project, $account] = linkedinSetup();
    Http::fake(['api.linkedin.com/rest/posts' => Http::response('nope', 422)]);

    $post = SocialPost::factory()->linkedin()->create(['project_id' => $project->id]);

    $this->actingAs($user)->from(route('social.posts.index', 'linkedin'))
        ->post(route('social.posts.approve', $post), ['social_account_id' => $account->id]);

    expect($post->fresh())
        ->status->toBe(SocialPostStatus::Draft)
        ->last_error->toContain('LinkedIn refused the post');
});

it('rejects the other side of a pair when an X post is marked posted', function () {
    [$user, $project] = linkedinSetup();
    [$named, $anonymized] = clientWinPair($project, 'x');

    $this->actingAs($user)->from(route('social.posts.index', 'x'))
        ->post(route('social.posts.publish', $named), ['url' => 'https://x.com/acme/status/42']);

    expect($named->fresh()->status)->toBe(SocialPostStatus::Published)
        ->and($anonymized->fresh()->status)->toBe(SocialPostStatus::Rejected);
});

it('redirects the old LinkedIn queue address to the new one', function () {
    [$user] = linkedinSetup();

    $this->actingAs($user)->get('/app/'.app(CurrentProject::class)->getOrFail()->slug.'/linkedin/posts')
        ->assertRedirect(route('social.posts.index', 'linkedin'));
});
