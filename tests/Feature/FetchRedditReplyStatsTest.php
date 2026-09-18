<?php

use App\Actions\FetchRedditReplyStats;
use App\Enums\RedditReplyExampleSource;
use App\Enums\RedditReplyStatus;
use App\Models\RedditReply;
use App\Models\RedditReplyExample;
use App\Support\Settings;
use Illuminate\Support\Facades\Http;

function lowerScoreThreshold(int $minScore = 5): void
{
    app(Settings::class)->set('reddit_examples.min_score', $minScore);
}

/**
 * The `.json` payload as Chrome's own JSON viewer wraps it - a `<pre>`
 * around the exact Reddit `[post_listing, comment_listing]` shape, since
 * `FlareSolverrRenderer` drives a real headless browser rather than
 * returning raw JSON. See `FetchRedditReplyStats::fetchScore()`.
 */
function fakeScorePage(int $score): string
{
    $json = json_encode([
        ['data' => ['children' => []]],
        ['data' => ['children' => [['data' => ['ups' => $score]]]]],
    ]);

    return "<html><body><pre>{$json}</pre></body></html>";
}

beforeEach(function () {
    config()->set('eveil.sources.flaresolverr.url', 'http://flaresolverr:8191');
    config()->set('eveil.sources.flaresolverr.max_timeout_ms', 60_000);
});

it('never checks a reply with no self-reported comment link', function () {
    lowerScoreThreshold();
    RedditReply::factory()->create([
        'status' => RedditReplyStatus::Published,
        'comment_permalink' => null,
        'published_at' => now(),
    ]);
    Http::fake();

    app(FetchRedditReplyStats::class)->handle();

    Http::assertNothingSent();
});

it('records the score without promoting when under the threshold', function () {
    lowerScoreThreshold(minScore: 20);
    $reply = RedditReply::factory()->create([
        'status' => RedditReplyStatus::Published,
        'comment_permalink' => 'https://www.reddit.com/r/selfhosted/comments/abc/comment/xyz/',
        'published_at' => now(),
    ]);
    Http::fake(['flaresolverr:8191/v1' => Http::response([
        'status' => 'ok',
        'solution' => ['response' => fakeScorePage(3)],
    ])]);

    app(FetchRedditReplyStats::class)->handle();

    expect($reply->fresh()->score)->toBe(3)
        ->and($reply->fresh()->stats_checked_at)->not->toBeNull()
        ->and($reply->fresh()->promoted_at)->toBeNull()
        ->and(RedditReplyExample::count())->toBe(0);
});

it('promotes a reply into the shared pool once it crosses the score threshold', function () {
    lowerScoreThreshold(minScore: 20);
    $reply = RedditReply::factory()->create([
        'status' => RedditReplyStatus::Published,
        'comment_permalink' => 'https://www.reddit.com/r/selfhosted/comments/abc/comment/xyz/',
        'published_at' => now(),
        'body' => 'A reply that took off.',
    ]);
    Http::fake(['flaresolverr:8191/v1' => Http::response([
        'status' => 'ok',
        'solution' => ['response' => fakeScorePage(42)],
    ])]);

    $promoted = app(FetchRedditReplyStats::class)->handle();

    $example = RedditReplyExample::sole();

    expect($promoted)->toBe(1)
        ->and($example->body)->toBe('A reply that took off.')
        ->and($example->source)->toBe(RedditReplyExampleSource::Promoted)
        ->and($example->reddit_reply_id)->toBe($reply->id)
        ->and($reply->fresh()->score)->toBe(42)
        ->and($reply->fresh()->promoted_at)->not->toBeNull();
});

it('never checks a reply that has already been promoted', function () {
    lowerScoreThreshold();
    RedditReply::factory()->create([
        'status' => RedditReplyStatus::Published,
        'comment_permalink' => 'https://www.reddit.com/r/selfhosted/comments/abc/comment/xyz/',
        'published_at' => now(),
        'promoted_at' => now(),
    ]);
    Http::fake();

    app(FetchRedditReplyStats::class)->handle();

    Http::assertNothingSent();
});

it('never checks a draft or a reply older than 30 days', function () {
    lowerScoreThreshold();
    RedditReply::factory()->create([
        'status' => RedditReplyStatus::Draft,
        'comment_permalink' => 'https://www.reddit.com/r/selfhosted/comments/abc/comment/xyz/',
    ]);
    RedditReply::factory()->create([
        'status' => RedditReplyStatus::Published,
        'comment_permalink' => 'https://www.reddit.com/r/selfhosted/comments/def/comment/uvw/',
        'published_at' => now()->subDays(40),
    ]);
    Http::fake();

    app(FetchRedditReplyStats::class)->handle();

    Http::assertNothingSent();
});

it('skips a reply, unpromoted, when FlareSolverr is not configured', function () {
    config()->set('eveil.sources.flaresolverr.url', null);
    lowerScoreThreshold();
    $reply = RedditReply::factory()->create([
        'status' => RedditReplyStatus::Published,
        'comment_permalink' => 'https://www.reddit.com/r/selfhosted/comments/abc/comment/xyz/',
        'published_at' => now(),
    ]);
    Http::fake();

    app(FetchRedditReplyStats::class)->handle();

    Http::assertNothingSent();
    expect($reply->fresh()->stats_checked_at)->toBeNull();
});
