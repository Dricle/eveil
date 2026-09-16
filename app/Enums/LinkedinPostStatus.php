<?php

namespace App\Enums;

/**
 * Three real states, not five. `Approved` never existed as its own state:
 * approving a draft calls the publish action directly. `Failed` is
 * deliberately absent too: a failed publish attempt leaves the row at
 * `Draft` with `last_error` populated, so the fact it was a draft awaiting
 * review is never destroyed by an API error - it just stays retryable.
 */
enum LinkedinPostStatus: string
{
    case Draft = 'draft';
    case Published = 'published';
    case Rejected = 'rejected';
}
