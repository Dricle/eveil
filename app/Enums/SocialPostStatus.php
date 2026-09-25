<?php

namespace App\Enums;

/**
 * Same three states as `LinkedinPostStatus`. On X, `Published` is
 * self-reported: the user posted it by hand and gave its URL back.
 */
enum SocialPostStatus: string
{
    case Draft = 'draft';
    case Published = 'published';
    case Rejected = 'rejected';
}
