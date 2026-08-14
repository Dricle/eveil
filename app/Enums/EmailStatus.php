<?php

namespace App\Enums;

enum EmailStatus: string
{
    case Valid = 'valid';
    case Risky = 'risky';
    case Unknown = 'unknown';
    case Invalid = 'invalid';
}
