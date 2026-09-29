<?php

use App\Models\SocialPost;
use App\Models\SocialPostExample;
use Illuminate\Database\QueryException;

/**
 * `social_post_examples_social_post_id_unique` mirrors
 * `email_examples_step_variant_id_unique`: a `social_post_id` may only back
 * one example row, but many rows may have no post behind them at all (a
 * superadmin's manually pasted example).
 */
it('refuses a second example for the same promoted post', function () {
    $post = SocialPost::factory()->create();
    SocialPostExample::factory()->create(['social_post_id' => $post->id]);

    SocialPostExample::factory()->create(['social_post_id' => $post->id]);
})->throws(QueryException::class);

it('allows many manually added examples with no post behind them', function () {
    SocialPostExample::factory()->create(['social_post_id' => null]);
    SocialPostExample::factory()->create(['social_post_id' => null]);

    expect(SocialPostExample::query()->count())->toBe(2);
});
