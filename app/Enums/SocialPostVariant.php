<?php

namespace App\Enums;

/**
 * Which side of a client-win pair a post is. Both are drafted in one call;
 * approving one rejects the other, and autonomous publishing only ever sends
 * the anonymized one, since naming a client in public is theirs to agree to.
 */
enum SocialPostVariant: string
{
    case Named = 'named';
    case Anonymized = 'anonymized';
}
