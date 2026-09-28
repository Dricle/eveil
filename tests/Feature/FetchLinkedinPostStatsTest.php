<?php

use App\Actions\FetchSocialPostStats;
use App\Enums\SocialPostExampleSource;
use App\Enums\SocialPostStatus;
use App\Models\SocialAccount;
use App\Models\SocialPost;
use App\Models\SocialPostExample;
use App\Support\Settings;
use Illuminate\Support\Facades\Http;

/**
 * LinkedIn's side of `FetchSocialPostStats`: its numbers need the account's
 * separate performance-polling token, which most accounts never have.
 */
function publishedLinkedinPost(?string $statsToken, array $attributes = []): SocialPost
{
    $account = SocialAccount::factory()->linkedin()->create(['stats_secret' => $statsToken]);

    return SocialPost::factory()->linkedin()->create([
        'social_account_id' => $account->id,
        'status' => SocialPostStatus::Published,
        'external_id' => 'urn:li:share:1',
        'published_at' => now(),
        ...$attributes,
    ]);
}

beforeEach(function () {
    app(Settings::class)->set('social_examples.linkedin.min_likes', 20);
});

it('never checks an account that never connected performance polling', function () {
    publishedLinkedinPost(statsToken: null);
    Http::fake();

    app(FetchSocialPostStats::class)->handle();

    Http::assertNothingSent();
});

it('records the like count without promoting when under the threshold', function () {
    $post = publishedLinkedinPost(statsToken: 'token');
    Http::fake(['api.linkedin.com/*' => Http::response(['reactionSummaries' => [['count' => 3]]])]);

    app(FetchSocialPostStats::class)->handle();

    expect($post->fresh())
        ->likes_count->toBe(3)
        ->stats_checked_at->not->toBeNull()
        ->promoted_at->toBeNull()
        ->and(SocialPostExample::count())->toBe(0);
});

it('promotes a post into the shared LinkedIn bank once it crosses the threshold', function () {
    $post = publishedLinkedinPost(statsToken: 'token');
    Http::fake(['api.linkedin.com/*' => Http::response(['reactionSummaries' => [['count' => 15], ['count' => 10]]])]);

    expect(app(FetchSocialPostStats::class)->handle())->toBe(1)
        ->and($post->fresh()->promoted_at)->not->toBeNull()
        ->and(SocialPostExample::sole())
        ->social_post_id->toBe($post->id)
        ->source->toBe(SocialPostExampleSource::Promoted);
});

it('never checks a draft or a post older than 30 days', function () {
    publishedLinkedinPost(statsToken: 'token', attributes: ['status' => SocialPostStatus::Draft, 'published_at' => null]);
    publishedLinkedinPost(statsToken: 'token', attributes: ['published_at' => now()->subDays(31)]);
    Http::fake();

    app(FetchSocialPostStats::class)->handle();

    Http::assertNothingSent();
});
