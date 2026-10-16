<?php

namespace App\Services\Ads;

use App\Enums\AdStatus;
use App\Models\Ad;
use App\Models\AdAccount;
use App\Models\AdCampaign;
use App\Models\PlatformApp;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

/**
 * Everything Eveil needs from an ad network, resolved from an account's
 * platform by `AdPlatform::client()`. Adding a network is an enum case and one
 * class implementing this: the actions, the agent, the budget guard and the
 * screens never name a network.
 *
 * Methods throw `AdPlatformException` when the network refuses, with a
 * sentence a person can act on.
 */
interface AdClientInterface
{
    /**
     * What this network accepts, read by the agent before it writes and by
     * the tools before anything is stored.
     */
    public function capabilities(): AdCapabilities;

    public function authorizationUrl(PlatformApp $app, string $redirectUri, string $state): string;

    public function exchangeCode(PlatformApp $app, string $code, string $redirectUri): AdTokens;

    public function refreshTokens(PlatformApp $app, string $refreshToken): AdTokens;

    /**
     * The ad accounts these tokens can manage.
     *
     * @return Collection<int, AdAccountData>
     */
    public function accounts(AdTokens $tokens): Collection;

    /**
     * Whether the network counts conversions for this account, i.e. the
     * user's site carries its tag.
     */
    public function hasConversionTracking(AdAccount $account): bool;

    /**
     * Creates the campaign, its targeting and its draft ads on the network,
     * PAUSED: nothing spends until `setCampaignStatus()` says so, so a launch
     * that fails halfway never leaves money running.
     */
    public function createCampaign(AdCampaign $campaign): LaunchedCampaign;

    /**
     * Adds one ad to a campaign already on the network. Returns its id there.
     */
    public function createAd(Ad $ad): string;

    public function setCampaignStatus(AdCampaign $campaign, AdStatus $status): void;

    public function setAdStatus(Ad $ad, AdStatus $status): void;

    public function setDailyBudget(AdCampaign $campaign, int $micros): void;

    /**
     * One row per ad per day over the period, for every ad the account has.
     *
     * @return Collection<int, AdMetricRow>
     */
    public function metrics(AdAccount $account, CarbonImmutable $from, CarbonImmutable $to): Collection;
}
