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
