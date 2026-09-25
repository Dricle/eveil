<?php

use App\Enums\SocialPlatform;
use App\Enums\SocialPostExampleSource;
use App\Models\SocialPostExample;
use App\Models\User;
use App\Support\Settings;

it('keeps the banks away from an ordinary user', function () {
    $this->actingAs(User::factory()->create())
        ->get(route('app-settings.social-post-examples.index'))
        ->assertForbidden();
});

it('adds a pasted example to the bank of the network picked', function () {
    $this->actingAs(superAdmin())
        ->post(route('app-settings.social-post-examples.store'), ['platform' => 'x', 'body' => 'A proven X post.'])
        ->assertSessionHasNoErrors();

    expect(SocialPostExample::query()->sole())
        ->platform->toBe(SocialPlatform::X)
        ->source->toBe(SocialPostExampleSource::Manual)
        ->added_by_user_id->not->toBeNull();
});

it('refuses an example with no network', function () {
    $this->actingAs(superAdmin())
        ->post(route('app-settings.social-post-examples.store'), ['body' => 'Orphan.'])
        ->assertSessionHasErrors('platform');
});

it('deletes an example and saves the Bluesky threshold', function () {
    $example = SocialPostExample::factory()->create();

    $this->actingAs(superAdmin())->delete(route('app-settings.social-post-examples.destroy', $example));
    $this->actingAs(superAdmin())->put(route('app-settings.social-post-examples.threshold'), ['min_likes' => 50]);

    expect(SocialPostExample::query()->count())->toBe(0)
        ->and(app(Settings::class)->int('social_examples.min_likes'))->toBe(50);
});

it('only ever samples the bank of the network being written for', function () {
    SocialPostExample::factory()->create(['platform' => SocialPlatform::X, 'body' => 'X winner']);
    SocialPostExample::factory()->create(['platform' => SocialPlatform::Bluesky, 'body' => 'Bluesky winner']);

    expect(SocialPostExample::promptDigest(SocialPlatform::Bluesky))
        ->toContain('Bluesky winner')
        ->not->toContain('X winner');
});
