<?php

namespace App\Enums;

/**
 * The three ways a drafted reply can approach a thread. All three are
 * drafted in one `RedditReplyWriter` call per opportunity; the user picks
 * which (if any) to post, and choosing one rejects the other two
 * (`RedditReply::sibling()`).
 */
enum RedditReplyAngle: string
{
    case ValueComment = 'value_comment';
    case SoftMention = 'soft_mention';
    case DmInvite = 'dm_invite';
}
