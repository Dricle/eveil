<?php

use App\Enums\LinkedinPostExampleSource;
use App\Models\LinkedinPostExample;
use App\Models\User;
use App\Support\Settings;

it('keeps the bank away from an ordinary user', function () {
    $this->actingAs(User::factory()->create())
        ->get(route('app-settings.linkedin-post-examples.index'))
        ->assertForbidden();
});

it('adds a pasted example', function () {
    $this->actingAs(superAdmin())
        ->post(route('app-settings.linkedin-post-examples.store'), [
            'body' => 'A proven LinkedIn post.',
        ])
        ->assertSessionHasNoErrors();

    $example = LinkedinPostExample::query()->sole();

    expect($example->body)->toBe('A proven LinkedIn post.')
        ->and($example->source)->toBe(LinkedinPostExampleSource::Manual)
        ->and($example->added_by_user_id)->not->toBeNull();
});

it('refuses an empty example', function () {
    $this->actingAs(superAdmin())
        ->post(route('app-settings.linkedin-post-examples.store'), [])
        ->assertSessionHasErrors('body');
});

it('deletes an example', function () {
    $example = LinkedinPostExample::factory()->create();

    $this->actingAs(superAdmin())
        ->delete(route('app-settings.linkedin-post-examples.destroy', $example))
        ->assertSessionHasNoErrors();

    expect(LinkedinPostExample::query()->count())->toBe(0);
});

it('saves the promotion threshold', function () {
    $this->actingAs(superAdmin())
        ->put(route('app-settings.linkedin-post-examples.threshold'), ['min_likes' => 50])
        ->assertSessionHasNoErrors();

    expect(app(Settings::class)->int('linkedin_examples.min_likes'))->toBe(50);
});
