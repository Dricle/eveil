<?php

use App\Actions\FindSubreddits;
use App\Models\TargetProfile;
use Illuminate\Support\Facades\Http;

// `SubredditFinder::find()` always also fires a web search for candidate
// names (SearXNG) - empty by default here, these tests are about topics and
// guesses specifically.
beforeEach(function () {
    Http::fake(['searxng:8080/*' => Http::response(['results' => []])]);
});

it('uses the agent\'s own subreddit topics when it proposed some', function () {
    $targetProfile = TargetProfile::factory()->create([
        'criteria' => ['sectors' => ['friteries'], 'subreddit_topics' => ['saas']],
    ]);

    Http::fake([
        'arctic-shift.photon-reddit.com/*' => function ($request) {
            parse_str((string) parse_url($request->url(), PHP_URL_QUERY), $query);

            return Http::response(['data' => ($query['subreddit_prefix'] ?? null) === 'saas'
                ? [['display_name' => 'SaaS', 'subscribers' => 200_000, 'public_description' => '', 'over18' => false, 'quarantine' => false]]
                : []]);
        },
    ]);

    $resolved = app(FindSubreddits::class)->handle($targetProfile);

    expect($resolved->criteria['subreddits'])->toHaveCount(1)
        ->and($resolved->criteria['subreddits'][0]['name'])->toBe('SaaS');
});

it('falls back to the profile\'s own sectors when it has no subreddit topics at all', function () {
    // A human-authored profile - one built by hand, never through
    // `TargetProfileDeriver` - has no `subreddit_topics` key to read.
    $targetProfile = TargetProfile::factory()->create([
        'criteria' => ['sectors' => ['saas']],
    ]);

    Http::fake([
        'arctic-shift.photon-reddit.com/*' => function ($request) {
            parse_str((string) parse_url($request->url(), PHP_URL_QUERY), $query);

            return Http::response(['data' => ($query['subreddit_prefix'] ?? null) === 'saas'
                ? [['display_name' => 'SaaS', 'subscribers' => 200_000, 'public_description' => '', 'over18' => false, 'quarantine' => false]]
                : []]);
        },
    ]);

    $resolved = app(FindSubreddits::class)->handle($targetProfile);

    expect($resolved->criteria['subreddits'])->toHaveCount(1);
});

it('also resolves the agent\'s guessed subreddit names, verified by exact match', function () {
    $targetProfile = TargetProfile::factory()->create([
        'criteria' => ['subreddit_topics' => [], 'subreddit_guesses' => ['coldemail']],
    ]);

    Http::fake([
        'arctic-shift.photon-reddit.com/*' => function ($request) {
            parse_str((string) parse_url($request->url(), PHP_URL_QUERY), $query);

            return Http::response(['data' => ($query['subreddit'] ?? null) === 'coldemail'
                ? [['display_name' => 'coldemail', 'subscribers' => 15_000, 'public_description' => '', 'over18' => false, 'quarantine' => false]]
                : []]);
        },
    ]);

    $resolved = app(FindSubreddits::class)->handle($targetProfile);

    expect($resolved->criteria['subreddits'])->toHaveCount(1)
        ->and($resolved->criteria['subreddits'][0]['name'])->toBe('coldemail');
});

it('stores an empty list rather than erroring when there is nothing to search on', function () {
    $targetProfile = TargetProfile::factory()->create(['criteria' => []]);

    Http::fake(['arctic-shift.photon-reddit.com/*' => Http::response(['data' => []])]);

    $resolved = app(FindSubreddits::class)->handle($targetProfile);

    expect($resolved->criteria['subreddits'])->toBe([]);

    Http::assertNothingSent();
});

it('re-resolving overwrites rather than accumulates', function () {
    $targetProfile = TargetProfile::factory()->create([
        'criteria' => ['subreddit_topics' => ['saas']],
    ]);

    Http::fake(['arctic-shift.photon-reddit.com/*' => Http::response(['data' => [
        ['display_name' => 'SaaS', 'subscribers' => 200_000, 'public_description' => '', 'over18' => false, 'quarantine' => false],
    ]])]);

    $action = app(FindSubreddits::class);
    $action->handle($targetProfile);
    $resolved = $action->handle($targetProfile->fresh());

    expect($resolved->criteria['subreddits'])->toHaveCount(1);
});
