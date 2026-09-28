<?php

namespace App\Enums;

/**
 * No `approved` or `failed`: approving IS publishing, and a failed publish
 * stays `Draft` with `last_error` set, so the same button retries. On X,
 * `Published` is self-reported: the user posted it by hand and gave its URL
 * back.
 */
enum SocialPostStatus: string
{
    case Draft = 'draft';
    case Published = 'published';
    case Rejected = 'rejected';
}
