<?php

use App\Actions\FetchLinkedinPostStats;
use App\Enums\LinkedinPostExampleSource;
use App\Enums\LinkedinPostStatus;
use App\Models\LinkedinAccount;
use App\Models\LinkedinPost;
use App\Models\LinkedinPostExample;
use App\Support\Settings;
use Illuminate\Support\Facades\Http;

function lowerLikesThreshold(int $minLikes = 5): void
{
    app(Settings::class)->set('linkedin_examples.min_likes', $minLikes);
}

it('never checks an account that never connected performance polling', function () {
    lowerLikesThreshold();
    $account = LinkedinAccount::factory()->create(['stats_access_token' => null]);
    LinkedinPost::factory()->create([
        'linkedin_account_id' => $account->id,
        'status' => LinkedinPostStatus::Published,
        'urn' => 'urn:li:share:1',
        'published_at' => now(),
    ]);
    Http::fake();

    app(FetchLinkedinPostStats::class)->handle();

    Http::assertNothingSent();
});

it('records the like count without promoting when under the threshold', function () {
    lowerLikesThreshold(minLikes: 20);
    $account = LinkedinAccount::factory()->create(['stats_access_token' => 'token']);
    $post = LinkedinPost::factory()->create([
        'linkedin_account_id' => $account->id,
        'status' => LinkedinPostStatus::Published,
        'urn' => 'urn:li:share:1',
        'published_at' => now(),
    ]);
    Http::fake(['api.linkedin.com/*' => Http::response([
        'reactionSummaries' => [['count' => 3]],
    ])]);

    app(FetchLinkedinPostStats::class)->handle();

    expect($post->fresh()->likes_count)->toBe(3)
        ->and($post->fresh()->stats_checked_at)->not->toBeNull()
        ->and($post->fresh()->promoted_at)->toBeNull()
        ->and(LinkedinPostExample::count())->toBe(0);
});

it('promotes a post into the shared pool once it crosses the like threshold', function () {
    lowerLikesThreshold(minLikes: 20);
    $account = LinkedinAccount::factory()->create(['stats_access_token' => 'token']);
    $post = LinkedinPost::factory()->create([
        'linkedin_account_id' => $account->id,
        'status' => LinkedinPostStatus::Published,
        'urn' => 'urn:li:share:1',
        'published_at' => now(),
        'body' => 'A post that took off.',
    ]);
    Http::fake(['api.linkedin.com/*' => Http::response([
        'reactionSummaries' => [['count' => 15], ['count' => 10]],
    ])]);

    $promoted = app(FetchLinkedinPostStats::class)->handle();

    $example = LinkedinPostExample::sole();

    expect($promoted)->toBe(1)
        ->and($example->body)->toBe('A post that took off.')
        ->and($example->source)->toBe(LinkedinPostExampleSource::Promoted)
        ->and($example->linkedin_post_id)->toBe($post->id)
        ->and($post->fresh()->likes_count)->toBe(25)
        ->and($post->fresh()->promoted_at)->not->toBeNull();
});

it('never checks a post that has already been promoted', function () {
    lowerLikesThreshold();
    $account = LinkedinAccount::factory()->create(['stats_access_token' => 'token']);
    LinkedinPost::factory()->create([
        'linkedin_account_id' => $account->id,
        'status' => LinkedinPostStatus::Published,
        'urn' => 'urn:li:share:1',
        'published_at' => now(),
        'promoted_at' => now(),
    ]);
    Http::fake();

    app(FetchLinkedinPostStats::class)->handle();

    Http::assertNothingSent();
});

it('never checks a draft or a post older than 30 days', function () {
    lowerLikesThreshold();
    $account = LinkedinAccount::factory()->create(['stats_access_token' => 'token']);
    LinkedinPost::factory()->create([
        'linkedin_account_id' => $account->id,
        'status' => LinkedinPostStatus::Draft,
    ]);
    LinkedinPost::factory()->create([
        'linkedin_account_id' => $account->id,
        'status' => LinkedinPostStatus::Published,
        'urn' => 'urn:li:share:2',
        'published_at' => now()->subDays(40),
    ]);
    Http::fake();

    app(FetchLinkedinPostStats::class)->handle();

    Http::assertNothingSent();
});
