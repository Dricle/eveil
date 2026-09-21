<?php

namespace App\Enums;

/**
 * The three ways a drafted reply can approach a thread, plus `UserWritten`
 * for a reply the user composed themselves instead of using any of the
 * three. All three drafted angles come from one `RedditReplyWriter` call
 * per opportunity; the user picks which (if any) to post, and choosing one
 * (drafted or self-written) rejects the other drafts (`RedditReply::sibling()`,
 * `App\Actions\SubmitManualRedditReply`).
 */
enum RedditReplyAngle: string
{
    case ValueComment = 'value_comment';
    case SoftMention = 'soft_mention';
    case DmInvite = 'dm_invite';
    case UserWritten = 'user_written';
}
