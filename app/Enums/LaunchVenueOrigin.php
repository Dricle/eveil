<?php

namespace App\Enums;

/**
 * Where a launch venue came from: the shared catalog, typed in by the user
 * for one project, or found by Eveil for one project (the subreddits its
 * target profiles turned up).
 */
enum LaunchVenueOrigin: string
{
    case Catalog = 'catalog';
    case User = 'user';
    case Ai = 'ai';
}
