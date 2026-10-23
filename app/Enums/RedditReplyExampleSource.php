<?php

namespace App\Enums;

/**
 * Where a row in the shared `reddit_reply_examples` pool came from. A user's
 * own "mark as proven" click is never one of these - it only stamps
 * `reddit_replies.promoted_at`, feeding that same project's own future
 * prompts.
 *
 * `Manual` and `Promoted` are ours: a superadmin typing one in by hand, or
 * `FetchRedditReplyStats` crossing the real, externally-measured score
 * threshold. `Harvested` is not: it is another redditor's own comment, kept
 * by `HarvestRedditReplyExamples` because it scored well while recommending
 * a product. The writer is shown the two groups separately and told which is
 * which - see `RedditReplyExample::promptDigest()`.
 */
enum RedditReplyExampleSource: string
{
    case Manual = 'manual';
    case Promoted = 'promoted';
    case Harvested = 'harvested';
}
