<?php

namespace App\Enums;

enum EmailAccountStatus: string
{
    case Active = 'active';
    case Paused = 'paused';
    case Error = 'error';
}
