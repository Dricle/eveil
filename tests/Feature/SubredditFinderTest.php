<?php

use App\Services\Discovery\SubredditFinder;
use Illuminate\Support\Facades\Http;

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

it('keeps a real, active, safe-for-work subreddit', function () {
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
