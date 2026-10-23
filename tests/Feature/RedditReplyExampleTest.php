<?php

use App\Enums\RedditReplyExampleSource;
use App\Models\RedditReplyExample;
use App\Models\User;
use App\Support\Settings;

it('keeps the bank away from an ordinary user', function () {
    $this->actingAs(User::factory()->create())
        ->get(route('app-settings.reddit-reply-examples.index'))
        ->assertForbidden();
});

it('adds a pasted example', function () {
    $this->actingAs(superAdmin())
        ->post(route('app-settings.reddit-reply-examples.store'), [
            'body' => 'A proven Reddit reply.',
        ])
        ->assertSessionHasNoErrors();

    $example = RedditReplyExample::query()->sole();

    expect($example->body)->toBe('A proven Reddit reply.')
        ->and($example->source)->toBe(RedditReplyExampleSource::Manual)
        ->and($example->added_by_user_id)->not->toBeNull();
});

it('refuses an empty example', function () {
    $this->actingAs(superAdmin())
        ->post(route('app-settings.reddit-reply-examples.store'), [])
        ->assertSessionHasErrors('body');
});

it('deletes an example', function () {
    $example = RedditReplyExample::factory()->create();

    $this->actingAs(superAdmin())
        ->delete(route('app-settings.reddit-reply-examples.destroy', $example))
        ->assertSessionHasNoErrors();

    expect(RedditReplyExample::query()->count())->toBe(0);
});

it('saves the promotion threshold', function () {
    $this->actingAs(superAdmin())
        ->put(route('app-settings.reddit-reply-examples.threshold'), ['min_score' => 50])
        ->assertSessionHasNoErrors();

    expect(app(Settings::class)->int('reddit_examples.min_score'))->toBe(50);
});

it('saves the harvest settings', function () {
    $this->actingAs(superAdmin())
        ->put(route('app-settings.reddit-reply-examples.harvest'), ['enabled' => '1', 'min_score' => 60])
        ->assertSessionHasNoErrors();

    expect(app(Settings::class)->bool('reddit_examples.harvest_enabled'))->toBeTrue()
        ->and(app(Settings::class)->int('reddit_examples.harvest_min_score'))->toBe(60);
});

it('switches the harvest off when the box is left unchecked', function () {
    // An unchecked checkbox sends nothing at all, so "absent" has to mean
    // off rather than "leave it as it was".
    $this->actingAs(superAdmin())
        ->put(route('app-settings.reddit-reply-examples.harvest'), ['min_score' => 60])
        ->assertSessionHasNoErrors();

    expect(app(Settings::class)->bool('reddit_examples.harvest_enabled'))->toBeFalse();
});

it('shows a harvested example with the comment it came from', function () {
    RedditReplyExample::factory()->create([
        'source' => RedditReplyExampleSource::Harvested,
        'reddit_comment_id' => 'cmt1',
        'comment_permalink' => 'https://www.reddit.com/r/smallbusiness/comments/abc123/best_tools/cmt1/',
        'comment_author' => 'someone',
        'subreddit' => 'smallbusiness',
        'score' => 40,
        'harvest_reason' => 'Explains the trade-off behind the recommendation.',
    ]);

    $this->actingAs(superAdmin())
        ->get(route('app-settings.reddit-reply-examples.index'))
        ->assertInertia(fn ($page) => $page
            ->where('examples.0.source', RedditReplyExampleSource::Harvested->value)
            ->where('examples.0.comment_author', 'someone')
            ->where('examples.0.subreddit', 'smallbusiness')
            ->where('examples.0.score', 40)
            ->where('examples.0.harvest_reason', 'Explains the trade-off behind the recommendation.')
            ->where('harvest.enabled', true)
            ->where('harvest.min_score', 25));
});
