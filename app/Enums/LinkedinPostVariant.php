<?php

namespace App\Enums;

/**
 * Only populated on a `client_won` pair: the writer produces both in one
 * call, and the user picks which goes out. Null everywhere else.
 */
enum LinkedinPostVariant: string
{
    case Named = 'named';
    case Anonymized = 'anonymized';
}
