<?php

namespace App\Actions;

use App\Enums\RedditReplyStatus;
use App\Models\RedditReply;

/**
 * The only "publish" step this feature has: no API call, because there is
 * no account to publish through - Reddit currently blocks new OAuth app
 * registration. Called after the user has copied the body and posted it on
 * reddit.com themselves. `comment_permalink` is optional: pasting it back
 * is what makes `FetchRedditReplyStats` able to poll a real score for this
 * row later, but skipping it is a fully valid "I posted this" too.
 */
class MarkRedditReplyPosted
{
    public function handle(RedditReply $reply, ?string $commentPermalink = null): void
    {
        $reply->update([
            'status' => RedditReplyStatus::Published,
            'published_at' => now(),
            'comment_permalink' => $commentPermalink,
        ]);
    }
}
