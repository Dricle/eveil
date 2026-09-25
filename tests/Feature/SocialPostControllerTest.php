<?php

use App\Enums\SocialAccountStatus;
use App\Enums\SocialPlatform;
use App\Enums\SocialPostFrequency;
use App\Enums\SocialPostStatus;
use App\Jobs\GenerateSocialPost;
use App\Models\Organization;
use App\Models\Project;
use App\Models\SocialAccount;
use App\Models\SocialPost;
use App\Models\User;
use App\Support\CurrentProject;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;

function socialSetup(): array
{
    $user = User::factory()->create();
    $organization = Organization::factory()->create();
    $organization->users()->attach($user, ['role' => 'owner']);
    $project = Project::factory()->for($organization)->create();

    $account = SocialAccount::factory()->create(['organization_id' => $organization->id, 'handle' => 'acme.bsky.social']);
    $account->projects()->attach($project);

    app(CurrentProject::class)->set($project);

    return [$user, $project, $account];
}

function fakeBluesky(): void
{
    Http::fake([
        'bsky.social/xrpc/com.atproto.server.createSession' => Http::response(['accessJwt' => 'jwt', 'did' => 'did:plc:acme', 'handle' => 'acme.bsky.social']),
        'bsky.social/xrpc/com.atproto.repo.createRecord' => Http::response(['uri' => 'at://did:plc:acme/app.bsky.feed.post/3abc', 'cid' => 'x']),
        '*' => Http::response('', 404),
    ]);
}

it('publishes a Bluesky draft to the chosen account, with clickable link and hashtag', function () {
    [$user, $project, $account] = socialSetup();
    fakeBluesky();

    $post = SocialPost::factory()->create(['project_id' => $project->id, 'body' => 'Café ☕ https://acme.test #launch']);

    $this->actingAs($user)
        ->from(route('social.posts.index'))->post(route('social.posts.approve', $post), ['social_account_id' => $account->id])
        ->assertRedirect(route('social.posts.index'));

    expect($post->fresh())
        ->status->toBe(SocialPostStatus::Published)
        ->external_id->toBe('at://did:plc:acme/app.bsky.feed.post/3abc')
        ->url->toBe('https://bsky.app/profile/acme.bsky.social/post/3abc')
        ->social_account_id->toBe($account->id);

    Http::assertSent(function (Request $request): bool {
        if (! str_ends_with($request->url(), 'createRecord')) {
            return false;
        }

        $facets = $request['record']['facets'];

        // "Café ☕ " is 10 bytes: é is 2, the cup is 3.
        return $facets[0]['index'] === ['byteStart' => 10, 'byteEnd' => 27]
            && $facets[0]['features'][0]['uri'] === 'https://acme.test'
            && $facets[1]['index'] === ['byteStart' => 28, 'byteEnd' => 35]
            && $facets[1]['features'][0]['tag'] === 'launch';
    });
});

it('keeps the draft with the error, and flags the account, when Bluesky refuses the app password', function () {
    [$user, $project, $account] = socialSetup();
    Http::fake(['bsky.social/*' => Http::response(['error' => 'AuthenticationRequired', 'message' => 'Invalid identifier or password'], 401)]);

    $post = SocialPost::factory()->create(['project_id' => $project->id]);

    $this->actingAs($user)->from(route('social.posts.index'))->post(route('social.posts.approve', $post), ['social_account_id' => $account->id]);

    expect($post->fresh()->status)->toBe(SocialPostStatus::Draft)
        ->and($post->fresh()->last_error)->toContain('Invalid identifier or password')
        ->and($account->fresh()->status)->toBe(SocialAccountStatus::Error);
});

it('refuses to publish through an account not granted to this project', function () {
    [$user, $project] = socialSetup();
    $foreign = SocialAccount::factory()->create();
    Http::fake();

    $post = SocialPost::factory()->create(['project_id' => $project->id]);

    $this->actingAs($user)->from(route('social.posts.index'))
        ->post(route('social.posts.approve', $post), ['social_account_id' => $foreign->id])
        ->assertSessionHasErrors('social_account_id');

    expect($post->fresh()->status)->toBe(SocialPostStatus::Draft);
    Http::assertNothingSent();
});

it('never publishes an X draft through the API', function () {
    [$user, $project, $account] = socialSetup();
    Http::fake();

    $post = SocialPost::factory()->x()->create(['project_id' => $project->id]);

    $this->actingAs($user)->post(route('social.posts.approve', $post), ['social_account_id' => $account->id])->assertNotFound();

    Http::assertNothingSent();
});

it('marks an X draft as posted from the link the user pastes back', function () {
    [$user, $project] = socialSetup();

    $post = SocialPost::factory()->x()->create(['project_id' => $project->id]);

    $this->actingAs($user)->from(route('social.posts.index'))
        ->post(route('social.posts.publish', $post), ['url' => 'https://x.com/acme/status/1839201'])
        ->assertRedirect(route('social.posts.index'));

    expect($post->fresh())
        ->status->toBe(SocialPostStatus::Published)
        ->external_id->toBe('1839201')
        ->url->toBe('https://x.com/acme/status/1839201');
});

it('refuses a link that is not a post', function () {
    [$user, $project] = socialSetup();

    $post = SocialPost::factory()->x()->create(['project_id' => $project->id]);

    $this->actingAs($user)->from(route('social.posts.index'))
        ->post(route('social.posts.publish', $post), ['url' => 'https://x.com/acme'])
        ->assertSessionHasErrors('url');

    expect($post->fresh()->status)->toBe(SocialPostStatus::Draft);
});

it('edits a draft but never a published post', function () {
    [$user, $project] = socialSetup();

    $draft = SocialPost::factory()->create(['project_id' => $project->id]);
    $published = SocialPost::factory()->create(['project_id' => $project->id, 'status' => SocialPostStatus::Published, 'body' => 'Live']);

    $this->actingAs($user)->from(route('social.posts.index'))->put(route('social.posts.update', $draft), ['body' => 'Shorter']);
    $this->actingAs($user)->put(route('social.posts.update', $published), ['body' => 'Changed'])->assertNotFound();

    expect($draft->fresh()->body)->toBe('Shorter')
        ->and($published->fresh()->body)->toBe('Live');
});

it('rejects with a reason and promotes only a published post', function () {
    [$user, $project] = socialSetup();

    $draft = SocialPost::factory()->create(['project_id' => $project->id]);
    $published = SocialPost::factory()->x()->create(['project_id' => $project->id, 'status' => SocialPostStatus::Published]);

    $this->actingAs($user)->from(route('social.posts.index'))->post(route('social.posts.reject', $draft), ['reason' => 'Too salesy']);
    $this->actingAs($user)->from(route('social.posts.index'))->post(route('social.posts.promote', $draft));
    $this->actingAs($user)->from(route('social.posts.index'))->post(route('social.posts.promote', $published));

    expect($draft->fresh())
        ->status->toBe(SocialPostStatus::Rejected)
        ->rejection_reason->toBe('Too salesy')
        ->promoted_at->toBeNull()
        ->and($published->fresh()->promoted_at)->not->toBeNull();
});

it('cannot touch another project\'s post', function () {
    [$user] = socialSetup();
    $foreign = SocialPost::factory()->create();

    $this->actingAs($user)->delete(route('social.posts.destroy', $foreign))->assertNotFound();

    expect(SocialPost::query()->withoutGlobalScopes()->count())->toBe(1);
});

it('makes a network due now only when its cadence changed', function () {
    [$user, $project] = socialSetup();
    $project->update(['bluesky_post_frequency' => SocialPostFrequency::Weekly, 'bluesky_next_post_at' => now()->addDays(5)]);

    $this->actingAs($user)->put(route('social.posts.cadence'), ['x_post_frequency' => 'daily', 'bluesky_post_frequency' => 'weekly'])
        ->assertRedirect(route('social.posts.index'));

    $project->refresh();

    expect($project->x_post_frequency)->toBe(SocialPostFrequency::Daily)
        ->and($project->x_next_post_at->isToday())->toBeTrue()
        ->and($project->bluesky_next_post_at->isAfter(now()->addDays(4)))->toBeTrue();
});

it('queues a draft for the network asked for', function () {
    [$user, $project] = socialSetup();
    Queue::fake();

    $this->actingAs($user)->post(route('social.posts.generate'), ['platform' => 'x'])->assertRedirect(route('social.posts.index'));

    Queue::assertPushed(GenerateSocialPost::class, fn (GenerateSocialPost $job): bool => $job->platform === SocialPlatform::X && $job->project->is($project));
});

it('lists the project\'s posts and granted Bluesky accounts', function () {
    [$user, $project] = socialSetup();
    SocialPost::factory()->x()->create(['project_id' => $project->id]);
    SocialPost::factory()->create();

    $this->actingAs($user)->get(route('social.posts.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('social/Posts')
            ->has('posts', 1)
            ->has('blueskyAccounts', 1)
            ->where('frequencies.x', 'off'));
});
