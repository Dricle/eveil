<?php

use App\Ai\Agents\RedditThreadTriage;
use App\Models\Project;
use App\Services\Discovery\Sources\RedditSource;
use App\Support\CurrentProject;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    // The real pipeline always calls RedditSource::search() from inside
    // DiscoveryJob::handle()'s CurrentProject::run() wrapper - the triage
    // agent call needs one set the same way here.
    app(CurrentProject::class)->set(Project::factory()->create());
});

function redditPost(array $overrides = []): array
{
    return array_merge([
        'title' => 'Launched my SaaS today',
        'permalink' => '/r/SaaS/comments/abc123/launched_my_saas_today/',
        'subreddit' => 'SaaS',
        'author' => 'founder123',
        'is_self' => true,
        'url' => 'https://www.reddit.com/r/SaaS/comments/abc123/launched_my_saas_today/',
        'selftext' => '',
    ], $overrides);
}

function redditComment(array $overrides = []): array
{
    return array_merge([
        'permalink' => '/r/SaaS/comments/xyz789/what_do_you_use/def456/',
        'subreddit' => 'SaaS',
        'author' => 'buildercorp',
        'body' => '',
    ], $overrides);
}

/**
 * Both endpoints run on every probe: fakes below mostly only care about one,
 * so the other defaults to empty unless a test overrides it.
 */
function noItems(): array
{
    return ['data' => []];
}

it('builds a candidate from a link post pointing at the product itself', function () {
    Http::fake([
        'arctic-shift.photon-reddit.com/api/posts/search*' => Http::response(['data' => [
            redditPost(['is_self' => false, 'url' => 'https://myproduct.test/']),
        ]]),
        'arctic-shift.photon-reddit.com/api/comments/search*' => Http::response(noItems()),
    ]);

    $candidates = app(RedditSource::class)->search(['subreddit' => 'SaaS', 'query' => 'launched']);

    expect($candidates)->toHaveCount(1)
        ->and($candidates->first()->website)->toBe('https://myproduct.test/')
        ->and($candidates->first()->sourceUrl)->toBe('https://www.reddit.com/r/SaaS/comments/abc123/launched_my_saas_today/')
        ->and($candidates->first()->facts['reddit_subreddit'])->toBe('SaaS');
});

it('extracts the product link from a self post body', function () {
    Http::fake([
        'arctic-shift.photon-reddit.com/api/posts/search*' => Http::response(['data' => [
            redditPost(['selftext' => 'Check it out at https://myproduct.test/ and let me know what you think.']),
        ]]),
        'arctic-shift.photon-reddit.com/api/comments/search*' => Http::response(noItems()),
    ]);

    $candidates = app(RedditSource::class)->search(['subreddit' => 'SaaS']);

    expect($candidates)->toHaveCount(1)
        ->and($candidates->first()->website)->toBe('https://myproduct.test/');
});

it('extracts the product link from a comment reply, not just the post', function () {
    Http::fake([
        'arctic-shift.photon-reddit.com/api/posts/search*' => Http::response(noItems()),
        'arctic-shift.photon-reddit.com/api/comments/search*' => Http::response(['data' => [
            redditComment(['body' => 'I built one of those, https://mysite.test/ if you want to try it.']),
        ]]),
    ]);

    $candidates = app(RedditSource::class)->search(['subreddit' => 'SaaS']);

    expect($candidates)->toHaveCount(1)
        ->and($candidates->first()->website)->toBe('https://mysite.test/')
        ->and($candidates->first()->sourceUrl)->toBe('https://www.reddit.com/r/SaaS/comments/xyz789/what_do_you_use/def456/');
});

it('drops a self post with no external link and no triage-positive read', function () {
    Http::fake([
        'arctic-shift.photon-reddit.com/api/posts/search*' => Http::response(['data' => [
            redditPost(['selftext' => 'Just launched, no link yet, still setting up the domain.']),
        ]]),
        'arctic-shift.photon-reddit.com/api/comments/search*' => Http::response(noItems()),
    ]);
    RedditThreadTriage::fake([['items' => []]]);

    expect(app(RedditSource::class)->search(['subreddit' => 'SaaS']))->toHaveCount(0);
});

it('never treats reddit or imgur themselves as the product', function () {
    Http::fake([
        'arctic-shift.photon-reddit.com/api/posts/search*' => Http::response(['data' => [
            redditPost(['is_self' => false, 'url' => 'https://www.reddit.com/r/SaaS/comments/xyz']),
            redditPost(['selftext' => 'Screenshot: https://imgur.com/a/photo123']),
        ]]),
        'arctic-shift.photon-reddit.com/api/comments/search*' => Http::response(noItems()),
    ]);
    RedditThreadTriage::fake([['items' => []]]);

    expect(app(RedditSource::class)->search(['subreddit' => 'SaaS']))->toHaveCount(0);
});

it('reports a failed request instead of throwing', function () {
    Http::fake(['arctic-shift.photon-reddit.com/*' => Http::response(status: 503)]);

    $source = app(RedditSource::class);
    $candidates = $source->search(['subreddit' => 'SaaS']);

    expect($candidates)->toHaveCount(0)
        ->and($source->failures())->not->toBeEmpty();
});

it('resolves a triage-positive mention through the author\'s own post history', function () {
    Http::fake([
        'arctic-shift.photon-reddit.com/api/posts/search*' => function ($request) {
            parse_str((string) parse_url($request->url(), PHP_URL_QUERY), $query);

            if (($query['author'] ?? null) === 'founder123') {
                return Http::response(['data' => [
                    redditPost(['is_self' => false, 'url' => 'https://myproduct.test/', 'author' => 'founder123']),
                ]]);
            }

            return Http::response(['data' => [
                redditPost(['selftext' => 'Built an AI email tool, DM me if curious.', 'author' => 'founder123']),
            ]]);
        },
        'arctic-shift.photon-reddit.com/api/comments/search*' => Http::response(noItems()),
    ]);

    RedditThreadTriage::fake([['items' => [[
        'permalink' => 'https://www.reddit.com/r/SaaS/comments/abc123/launched_my_saas_today/',
        'is_candidate' => true,
        'product_identifier' => 'an AI email tool',
        'reason' => 'Author says they built an AI email tool.',
    ]]]]);

    $candidates = app(RedditSource::class)->search(['subreddit' => 'SaaS']);

    expect($candidates)->toHaveCount(1)
        ->and($candidates->first()->website)->toBe('https://myproduct.test/')
        ->and($candidates->first()->facts['reddit_reason'])->toBe('Author says they built an AI email tool.');
});

it('falls back to evidence when the author\'s own history has no link either', function () {
    Http::fake([
        'arctic-shift.photon-reddit.com/api/posts/search*' => function ($request) {
            parse_str((string) parse_url($request->url(), PHP_URL_QUERY), $query);

            if (($query['author'] ?? null) === 'founder123') {
                return Http::response(noItems());
            }

            return Http::response(['data' => [
                redditPost(['selftext' => 'Built an AI email tool, DM me if curious.', 'author' => 'founder123']),
            ]]);
        },
        'arctic-shift.photon-reddit.com/api/comments/search*' => Http::response(noItems()),
    ]);

    RedditThreadTriage::fake([['items' => [[
        'permalink' => 'https://www.reddit.com/r/SaaS/comments/abc123/launched_my_saas_today/',
        'is_candidate' => true,
        'product_identifier' => 'an AI email tool',
        'reason' => 'Author says they built an AI email tool.',
    ]]]]);

    $candidates = app(RedditSource::class)->search(['subreddit' => 'SaaS']);

    expect($candidates)->toHaveCount(1)
        ->and($candidates->first()->website)->toBeNull()
        ->and($candidates->first()->facts['evidence'])->not->toBeNull()
        ->and($candidates->first()->facts['reddit_reason'])->toBe('Author says they built an AI email tool.');
});

it('drops a triage-negative item entirely', function () {
    Http::fake([
        'arctic-shift.photon-reddit.com/api/posts/search*' => Http::response(['data' => [
            redditPost(['selftext' => 'What do you all use for cold email?']),
        ]]),
        'arctic-shift.photon-reddit.com/api/comments/search*' => Http::response(noItems()),
    ]);

    RedditThreadTriage::fake([['items' => [[
        'permalink' => 'https://www.reddit.com/r/SaaS/comments/abc123/launched_my_saas_today/',
        'is_candidate' => false,
        'product_identifier' => '',
        'reason' => 'Asking a question, not promoting anything.',
    ]]]]);

    expect(app(RedditSource::class)->search(['subreddit' => 'SaaS']))->toHaveCount(0);
});
