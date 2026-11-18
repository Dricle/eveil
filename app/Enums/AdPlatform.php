<?php

namespace App\Enums;

use App\Services\Ads\AdClientInterface;
use App\Services\Ads\GoogleAdsClient;
use App\Services\Ads\MetaAdsClient;
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
    case MetaAds = 'meta_ads';

    public function label(): string
    {
        return match ($this) {
            self::GoogleAds => 'Google Ads',
            self::MicrosoftAds => 'Microsoft Advertising',
            self::MetaAds => 'Meta Ads',
        };
    }

    /**
     * The guide to setting up this network's app, in the public docs.
     */
    public function docsUrl(): string
    {
        return 'https://docs.eveil.cloud/product/ads#'.match ($this) {
            self::GoogleAds => 'google-ads-getting-a-client-id-and-secret',
            self::MicrosoftAds => 'microsoft-advertising-getting-a-client-id-secret-and-developer-token',
            self::MetaAds => 'meta-ads-getting-an-app-id-and-secret',
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
            self::MetaAds => PlatformAppKind::MetaAds,
        };
    }

    public function client(): AdClientInterface
    {
        return app(match ($this) {
            self::GoogleAds => GoogleAdsClient::class,
            self::MicrosoftAds => MicrosoftAdsClient::class,
            self::MetaAds => MetaAdsClient::class,
        });
    }
}
