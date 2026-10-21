<?php

namespace App\Enums;

/**
 * What a `platform_apps` row is the OAuth app for. Wider than `AdPlatform`:
 * an organization's own Meta app for Instagram is a developer app like its
 * Google Ads one, registered by hand and resolved the same way (its own row
 * first, else the instance's), but Instagram is no ad network.
 */
enum PlatformAppKind: string
{
    case GoogleAds = 'google_ads';
    case Instagram = 'instagram';

    public function label(): string
    {
        return match ($this) {
            self::GoogleAds => 'Google Ads',
            self::Instagram => 'Meta (Instagram)',
        };
    }

    /**
     * The redirect URI registered by hand on the network's developer console.
     */
    public function redirectUri(): string
    {
        return match ($this) {
            self::GoogleAds => route('oauth.ads.callback', AdPlatform::GoogleAds),
            self::Instagram => route('oauth.instagram.callback'),
        };
    }
}
