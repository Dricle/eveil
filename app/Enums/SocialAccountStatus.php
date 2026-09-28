<?php

namespace App\Enums;

/**
 * `Expired`: a LinkedIn token that could not be refreshed. `Error`: the
 * network refused the stored credential outright (a revoked Bluesky app
 * password, a moved handle). Either way, reconnecting fixes it.
 */
enum SocialAccountStatus: string
{
    case Active = 'active';
    case Expired = 'expired';
    case Error = 'error';
}
