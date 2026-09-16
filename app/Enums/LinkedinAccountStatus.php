<?php

namespace App\Enums;

enum LinkedinAccountStatus: string
{
    case Active = 'active';
    case Expired = 'expired';
    case Error = 'error';
}
