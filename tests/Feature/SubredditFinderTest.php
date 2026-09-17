<?php

use App\Services\Discovery\SubredditFinder;
use Illuminate\Http\Client\Factory;
use Illuminate\Support\Facades\Http;

// `find()` always also fires a web search for candidate names (SearXNG) -
// empty by default here so a test that does not care about that signal never
// makes an unexpected extra assertion-breaking call.
beforeEach(function () {
    Http::fake(['searxng:8080/*' => Http::response(['results' => []])]);
});

function subreddit(array $overrides = []): array
{
    return array_merge([
        'display_name' => 'SaaS',
        'subscribers' => 200_000,
        'public_description' => 'For SaaS founders.',
        'over18' => false,
        'quarantine' => false,
    ], $overrides);
}

it('keeps a real, active, safe-for-work subreddit found by topic', function () {
    Http::fake(['arctic-shift.photon-reddit.com/*' => Http::response(['data' => [subreddit()]])]);

    $found = app(SubredditFinder::class)->find(['saas']);

    expect($found)->toHaveCount(1)
        ->and($found[0]['name'])->toBe('SaaS')
        ->and($found[0]['subscribers'])->toBe(200_000);
});

it('drops a community too small to be worth a probe', function () {
    Http::fake(['arctic-shift.photon-reddit.com/*' => Http::response(['data' => [
        subreddit(['display_name' => 'tinysaas', 'subscribers' => 12]),
    ]])]);

    expect(app(SubredditFinder::class)->find(['saas']))->toBe([]);
});

it('keeps an NSFW-tagged subreddit - no vertical exclusion, adult industry is a real market', function () {
    Http::fake(['arctic-shift.photon-reddit.com/*' => Http::response(['data' => [
        subreddit(['display_name' => 'nsfwsaas', 'over18' => true]),
    ]])]);

    expect(app(SubredditFinder::class)->find(['saas']))->toHaveCount(1);
});

it('drops a quarantined subreddit - Reddit\'s own flag for genuinely extreme or harmful content', function () {
    Http::fake(['arctic-shift.photon-reddit.com/*' => Http::response(['data' => [
        subreddit(['display_name' => 'quarantinedsaas', 'quarantine' => true]),
    ]])]);

    expect(app(SubredditFinder::class)->find(['saas']))->toBe([]);
});

it('dedupes the same subreddit found through two different topics and sorts by subscribers', function () {
    Http::fake([
        'arctic-shift.photon-reddit.com/*' => function ($request) {
            parse_str((string) parse_url($request->url(), PHP_URL_QUERY), $query);

            return Http::response(['data' => match ($query['subreddit_prefix'] ?? null) {
                'saas' => [subreddit(['display_name' => 'SaaS', 'subscribers' => 200_000])],
                'startup' => [
                    subreddit(['display_name' => 'SaaS', 'subscribers' => 200_000]),
                    subreddit(['display_name' => 'startups', 'subscribers' => 500_000]),
                ],
                default => [],
            }]);
        },
    ]);

    $found = app(SubredditFinder::class)->find(['saas', 'startup']);

    expect($found)->toHaveCount(2)
        ->and($found[0]['name'])->toBe('startups')
        ->and($found[1]['name'])->toBe('SaaS');
});

it('returns nothing instead of throwing when the request fails', function () {
    Http::fake(['arctic-shift.photon-reddit.com/*' => Http::response(status: 503)]);

    expect(app(SubredditFinder::class)->find(['saas']))->toBe([]);
});

it('resolves a guessed subreddit name by exact match, the way a multi-word topic never can', function () {
    Http::fake([
        'arctic-shift.photon-reddit.com/*' => function ($request) {
            parse_str((string) parse_url($request->url(), PHP_URL_QUERY), $query);

            // A phrase like "cold outreach" is never itself a subreddit-name
            // prefix (names carry no spaces) - the topic search below finds
            // nothing, and the guess is what resolves it.
            if (($query['subreddit'] ?? null) === 'coldemail') {
                return Http::response(['data' => [subreddit(['display_name' => 'coldemail', 'subscribers' => 15_000])]]);
            }

            return Http::response(['data' => []]);
        },
    ]);

    $found = app(SubredditFinder::class)->find(['cold outreach'], ['coldemail']);

    expect($found)->toHaveCount(1)
        ->and($found[0]['name'])->toBe('coldemail');
});

it('silently drops a guessed subreddit that does not exist or fails its floors', function () {
    Http::fake(['arctic-shift.photon-reddit.com/*' => Http::response(['data' => []])]);

    expect(app(SubredditFinder::class)->find([], ['definitely_not_a_real_subreddit']))->toBe([]);
});

it('never re-fetches a guess already resolved by topic', function () {
    $exactLookups = 0;

    Http::fake([
        'arctic-shift.photon-reddit.com/*' => function ($request) use (&$exactLookups) {
            parse_str((string) parse_url($request->url(), PHP_URL_QUERY), $query);

            if (($query['subreddit_prefix'] ?? null) === 'saas') {
                return Http::response(['data' => [subreddit(['display_name' => 'SaaS', 'subscribers' => 200_000])]]);
            }

            $exactLookups++;

            return Http::response(['data' => []]);
        },
    ]);

    $found = app(SubredditFinder::class)->find(['saas'], ['SaaS']);

    expect($found)->toHaveCount(1)
        ->and($exactLookups)->toBe(0);
});

it('extracts and verifies subreddit mentions from a web search for the topics', function () {
    // Overrides the beforeEach's empty default for this one test, per the
    // documented `Http::fake` accumulation trap: re-registering the same
    // pattern would just add a second stub that the first (empty) one, being
    // checked first, always wins over.
    Http::swap(new Factory);

    Http::fake([
        'searxng:8080/*' => Http::response(['results' => [
            ['title' => '27 Best Subreddits for SaaS Founders', 'content' => 'Check r/SaaS, r/SideProject and r/indiehackers for real traction.', 'url' => 'https://example.com/best-subreddits'],
        ]]),
        'arctic-shift.photon-reddit.com/*' => function ($request) {
            parse_str((string) parse_url($request->url(), PHP_URL_QUERY), $query);

            return Http::response(['data' => match ($query['subreddit'] ?? null) {
                'SideProject' => [subreddit(['display_name' => 'SideProject', 'subscribers' => 300_000])],
                default => [],
            }]);
        },
    ]);

    $found = app(SubredditFinder::class)->find(['saas']);

    expect($found)->toHaveCount(1)
        ->and($found[0]['name'])->toBe('SideProject');
});

it('never fires a web search when there are no topics to search on', function () {
    expect(app(SubredditFinder::class)->find([]))->toBe([]);

    Http::assertNothingSent();
});
