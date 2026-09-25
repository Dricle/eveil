<?php

namespace App\Enums;

/**
 * `Error` is set when Bluesky refuses the stored app password: the user
 * revoked it, or the handle moved. Reconnecting replaces it.
 */
enum SocialAccountStatus: string
{
    case Active = 'active';
    case Error = 'error';
}
