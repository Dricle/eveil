<?php

namespace App\Enums;

/**
 * What a launch venue is, which is how the checklist groups them. Cases are
 * in the order the checklist shows them: the venues that move the needle
 * first, the long tail of directories after.
 */
enum LaunchVenueKind: string
{
    case LaunchPlatform = 'launch_platform';
    case Community = 'community';
    case ReviewSite = 'review_site';
    case DealMarketplace = 'deal_marketplace';
    case Directory = 'directory';

    public function label(): string
    {
        return match ($this) {
            self::LaunchPlatform => 'Launch platforms',
            self::Community => 'Communities',
            self::ReviewSite => 'Review sites',
            self::DealMarketplace => 'Deal marketplaces',
            self::Directory => 'Directories',
        };
    }
}
