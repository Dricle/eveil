<?php

namespace App\Enums;

use App\Services\Ads\AdClientInterface;
use App\Services\Ads\GoogleAdsClient;
use App\Services\Ads\MicrosoftAdsClient;

/**
 * The ad networks Eveil runs campaigns on. Adding one is a case here and a
 * driver implementing `AdClientInterface`: nothing else names a network.
 *
 * Values are prefixed with what they are because `platform_apps` is shared:
 * LinkedIn's ads app and its posting app are two registrations.
 */
enum AdPlatform: string
{
    case GoogleAds = 'google_ads';
    case MicrosoftAds = 'microsoft_ads';

    public function label(): string
    {
        return match ($this) {
            self::GoogleAds => 'Google Ads',
            self::MicrosoftAds => 'Microsoft Advertising',
        };
    }

    /**
     * Which `platform_apps` row this network connects through.
     */
    public function appKind(): PlatformAppKind
    {
        return match ($this) {
            self::GoogleAds => PlatformAppKind::GoogleAds,
            self::MicrosoftAds => PlatformAppKind::MicrosoftAds,
        };
    }

    public function client(): AdClientInterface
    {
        return app(match ($this) {
            self::GoogleAds => GoogleAdsClient::class,
            self::MicrosoftAds => MicrosoftAdsClient::class,
        });
    }
}
