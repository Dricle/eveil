<?php

namespace App\Enums;

/**
 * Three real states, not five - copy of `LinkedinPostStatus`'s reasoning.
 * `Approved` never existed: marking a draft posted (or publishing it, on the
 * dropped-OAuth path this product no longer has) IS the approval. `Failed`
 * is deliberately absent too: nothing here can fail an API call any more
 * (there is no API call), but the same shape is kept for symmetry with the
 * rest of the drafting pipeline.
 */
enum RedditReplyStatus: string
{
    case Draft = 'draft';
    case Published = 'published';
    case Rejected = 'rejected';
}
