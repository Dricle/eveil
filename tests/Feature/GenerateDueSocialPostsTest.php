<?php

use App\Actions\FetchSocialPostStats;
use App\Actions\GenerateDueSocialPosts;
use App\Enums\SocialAccountStatus;
use App\Enums\SocialPlatform;
use App\Enums\SocialPostExampleSource;
use App\Enums\SocialPostFrequency;
use App\Enums\SocialPostStatus;
use App\Jobs\GenerateSocialPost;
use App\Models\Project;
use App\Models\SocialAccount;
use App\Models\SocialPost;
use App\Models\SocialPostExample;
use App\Models\User;
use App\Support\CurrentProject;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;

it('queues a due X post with no account at all, and moves the next date on', function () {
    Queue::fake();
    $project = Project::factory()->create(['x_post_frequency' => SocialPostFrequency::Weekly, 'x_next_post_at' => now()->subMinute()]);

    expect(app(GenerateDueSocialPosts::class)->handle())->toBe(1);

    Queue::assertPushed(GenerateSocialPost::class, fn (GenerateSocialPost $job): bool => $job->platform === SocialPlatform::X);
    expect($project->fresh()->x_next_post_at->isAfter(now()->addDays(6)))->toBeTrue();
});

it('skips a due Bluesky post until a working account is granted', function () {
    Queue::fake();
    $project = Project::factory()->create(['bluesky_post_frequency' => SocialPostFrequency::Daily, 'bluesky_next_post_at' => now()->subMinute()]);
    $account = SocialAccount::factory()->create(['organization_id' => $project->organization_id, 'status' => SocialAccountStatus::Error]);
    $account->projects()->attach($project);

    expect(app(GenerateDueSocialPosts::class)->handle())->toBe(0);

    $account->update(['status' => SocialAccountStatus::Active]);

    expect(app(GenerateDueSocialPosts::class)->handle())->toBe(1);
});

it('leaves a project whose cadence is off or not due yet alone', function () {
    Queue::fake();
    Project::factory()->create(['x_post_frequency' => SocialPostFrequency::Off, 'x_next_post_at' => now()->subDay()]);
    Project::factory()->create(['x_post_frequency' => SocialPostFrequency::Daily, 'x_next_post_at' => now()->addHour()]);

    expect(app(GenerateDueSocialPosts::class)->handle())->toBe(0);
});

it('reads Bluesky like counts and copies a post past the threshold into the shared bank, once', function () {
    $popular = SocialPost::factory()->create(['status' => SocialPostStatus::Published, 'external_id' => 'at://a/post/1', 'published_at' => now()->subDay()]);
    $quiet = SocialPost::factory()->create(['status' => SocialPostStatus::Published, 'external_id' => 'at://a/post/2', 'published_at' => now()->subDay()]);
    $x = SocialPost::factory()->x()->create(['status' => SocialPostStatus::Published, 'external_id' => '123', 'published_at' => now()->subDay()]);

    Http::fake(['public.api.bsky.app/*' => Http::response(['posts' => [
        ['uri' => 'at://a/post/1', 'likeCount' => 42],
        ['uri' => 'at://a/post/2', 'likeCount' => 3],
    ]])]);

    expect(app(FetchSocialPostStats::class)->handle())->toBe(1)
        ->and($popular->fresh()->likes_count)->toBe(42)
        ->and($popular->fresh()->promoted_at)->not->toBeNull()
        ->and($quiet->fresh()->likes_count)->toBe(3)
        ->and($quiet->fresh()->promoted_at)->toBeNull()
        ->and($x->fresh()->stats_checked_at)->toBeNull()
        ->and(SocialPostExample::query()->sole())
        ->social_post_id->toBe($popular->id)
        ->source->toBe(SocialPostExampleSource::Promoted);

    // The next day's poll must not copy it twice.
    expect(app(FetchSocialPostStats::class)->handle())->toBe(0)
        ->and(SocialPostExample::query()->count())->toBe(1);
});

it('lets a post the user already marked successful still earn its place in the bank', function () {
    $post = SocialPost::factory()->create(['status' => SocialPostStatus::Published, 'external_id' => 'at://a/post/9', 'published_at' => now()->subDay(), 'promoted_at' => now()->subHour()]);
    Http::fake(['public.api.bsky.app/*' => Http::response(['posts' => [['uri' => 'at://a/post/9', 'likeCount' => 99]]])]);

    app(FetchSocialPostStats::class)->handle();

    expect(SocialPostExample::query()->sole()->social_post_id)->toBe($post->id);
});

it('never writes to the shared bank from a user\'s own click', function () {
    $post = SocialPost::factory()->create(['status' => SocialPostStatus::Published]);
    $user = User::factory()->create();
    $post->project->organization->users()->attach($user, ['role' => 'owner']);
    app(CurrentProject::class)->set($post->project);

    $this->actingAs($user)->from(route('social.posts.index'))->post(route('social.posts.promote', $post));

    expect($post->fresh()->promoted_at)->not->toBeNull()
        ->and(SocialPostExample::query()->count())->toBe(0);
});
