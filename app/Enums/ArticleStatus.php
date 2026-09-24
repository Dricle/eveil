<?php

namespace App\Enums;

/**
 * Same three states as `LinkedinPostStatus`. `Published` is self-reported:
 * the user pasted the article into their own CMS and gave its URL back.
 */
enum ArticleStatus: string
{
    case Draft = 'draft';
    case Published = 'published';
    case Rejected = 'rejected';
}
