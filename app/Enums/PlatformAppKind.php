<?php

namespace App\Enums;

/**
 * What a `platform_apps` row is the OAuth app for. Wider than `AdPlatform`:
 * an organization's own Meta app for Instagram is a developer app like its
 * Google Ads one, registered by hand and resolved the same way (its own row
 * first, else the instance's), but Instagram is no ad network.
 *
 * LinkedIn's two apps (posting, and the Community Management one for post
 * stats, which LinkedIn refuses on the same app) are instance only for now:
 * `LinkedinCredentials` reads the instance rows, and an organization cannot
 * save its own (`bringYourOwn()`).
 */
enum PlatformAppKind: string
{
    case GoogleAds = 'google_ads';
    case MicrosoftAds = 'microsoft_ads';
    case MetaAds = 'meta_ads';
    case Instagram = 'instagram';
    case LinkedinPosting = 'linkedin_posting';
    case LinkedinStats = 'linkedin_stats';

    /**
     * The kinds an organization may save its own app for.
     *
     * @return list<self>
     */
    public static function bringYourOwn(): array
    {
        return [self::GoogleAds, self::MicrosoftAds, self::MetaAds, self::Instagram];
    }

    public function label(): string
    {
        return match ($this) {
            self::GoogleAds => 'Google Ads',
            self::MicrosoftAds => 'Microsoft Advertising',
            self::MetaAds => 'Meta Ads',
            self::Instagram => 'Meta (Instagram)',
            self::LinkedinPosting => 'LinkedIn',
            self::LinkedinStats => 'LinkedIn Community Management',
        };
    }

    /**
     * The redirect URI registered by hand on the network's developer console.
     */
    public function redirectUri(): string
    {
        return match ($this) {
            self::GoogleAds => route('oauth.ads.callback', AdPlatform::GoogleAds),
            self::MicrosoftAds => route('oauth.ads.callback', AdPlatform::MicrosoftAds),
            self::MetaAds => route('oauth.ads.callback', AdPlatform::MetaAds),
            self::Instagram => route('oauth.instagram.callback'),
            self::LinkedinPosting => route('oauth.linkedin.callback'),
            self::LinkedinStats => route('oauth.linkedin.stats.callback'),
        };
    }

    /**
     * Whether the network also wants a developer token beside the OAuth app
     * on every call. Google retired theirs; Microsoft still issues one per
     * developer account.
     */
    public function needsDeveloperToken(): bool
    {
        return $this === self::MicrosoftAds;
    }
}
