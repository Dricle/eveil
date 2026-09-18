<?php

namespace App\Enums;

/**
 * Copy of `LinkedinPostExampleSource`. Exactly two trusted sources feed the
 * shared `reddit_reply_examples` pool: a superadmin typing one in by hand,
 * or `FetchRedditReplyStats` crossing the real, externally-measured score
 * threshold. A user's own "mark as proven" click never writes here - it
 * only stamps `reddit_replies.promoted_at`, feeding that same project's own
 * future prompts.
 */
enum RedditReplyExampleSource: string
{
    case Manual = 'manual';
    case Promoted = 'promoted';
}
