<?php

namespace App\Enums;

/**
 * `Used` once something was made from it, `Dismissed` when the user said no:
 * neither comes back as a suggestion.
 */
enum IdeaStatus: string
{
    case Open = 'open';
    case Used = 'used';
    case Dismissed = 'dismissed';
}
