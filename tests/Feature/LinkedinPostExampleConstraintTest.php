<?php

use App\Models\LinkedinPost;
use App\Models\LinkedinPostExample;
use Illuminate\Database\QueryException;

/**
 * `linkedin_post_examples_linkedin_post_id_unique` mirrors
 * `email_examples_step_variant_id_unique`: a `linkedin_post_id` may only
 * back one example row, but many rows may have no post behind them at all
 * (a superadmin's manually pasted example).
 */
it('refuses a second example for the same promoted post', function () {
    $post = LinkedinPost::factory()->create();
    LinkedinPostExample::factory()->create(['linkedin_post_id' => $post->id]);

    LinkedinPostExample::factory()->create(['linkedin_post_id' => $post->id]);
})->throws(QueryException::class);

it('allows many manually added examples with no post behind them', function () {
    LinkedinPostExample::factory()->create(['linkedin_post_id' => null]);
    LinkedinPostExample::factory()->create(['linkedin_post_id' => null]);

    expect(LinkedinPostExample::query()->count())->toBe(2);
});
