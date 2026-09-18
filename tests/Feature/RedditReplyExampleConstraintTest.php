<?php

use App\Models\RedditReply;
use App\Models\RedditReplyExample;
use Illuminate\Database\QueryException;

/**
 * `reddit_reply_examples_reddit_reply_id_unique` mirrors
 * `linkedin_post_examples_linkedin_post_id_unique`: a `reddit_reply_id` may
 * only back one example row, but many rows may have no reply behind them at
 * all (a superadmin's manually pasted example).
 */
it('refuses a second example for the same promoted reply', function () {
    $reply = RedditReply::factory()->create();
    RedditReplyExample::factory()->create(['reddit_reply_id' => $reply->id]);

    RedditReplyExample::factory()->create(['reddit_reply_id' => $reply->id]);
})->throws(QueryException::class);

it('allows many manually added examples with no reply behind them', function () {
    RedditReplyExample::factory()->create(['reddit_reply_id' => null]);
    RedditReplyExample::factory()->create(['reddit_reply_id' => null]);

    expect(RedditReplyExample::query()->count())->toBe(2);
});
