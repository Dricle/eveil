<?php

namespace App\Enums;

/**
 * The classifier's read of an ad, shown to the agent next to the numbers as a
 * hint it may overrule. `Learning` until the ad has spent and been clicked
 * enough to say anything.
 */
enum AdVerdict: string
{
    case Learning = 'learning';
    case Winner = 'winner';
    case Loser = 'loser';
    case Flat = 'flat';
}
