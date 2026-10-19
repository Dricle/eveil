<?php

namespace App\Enums;

/**
 * `Error` means the network refused us (a revoked token, a suspended account):
 * nothing is synced or applied until the account is connected again.
 */
enum AdAccountStatus: string
{
    case Active = 'active';
    case Error = 'error';
}
