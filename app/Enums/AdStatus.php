<?php

namespace App\Enums;

/**
 * Where an ad campaign or an ad stands. `Draft` exists only in Eveil: proposed,
 * not yet created on its network. `Removed` is final, the way it is on every
 * network.
 */
enum AdStatus: string
{
    case Draft = 'draft';
    case Active = 'active';
    case Paused = 'paused';
    case Removed = 'removed';
}
