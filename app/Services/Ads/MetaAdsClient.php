<?php

namespace App\Services\Ads;

use App\Enums\AdStatus;
use App\Models\Ad;
use App\Models\AdAccount;
use App\Models\AdCampaign;
use App\Models\PlatformApp;
use Carbon\CarbonImmutable;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;

/**
 * Meta (Facebook and Instagram) over the Marketing API, no SDK.
 *
 * A campaign is a traffic campaign with its budget on the campaign, one ad
 * set holding the countries, and link ads posted as the Facebook Page picked
 * on the account (`identity_id`). Meta builds the picture from the landing
 * page's own preview image.
 *
 * Meta has no atomic batch: a launch refused halfway deletes the campaign,
 * which takes its ad set and ads with it.
 */
class MetaAdsClient implements AdClientInterface
{
    private const API = 'https://graph.facebook.com/v23.0';

    private const SCOPES = 'ads_management,ads_read,business_management,pages_show_list,pages_manage_ads';

    /**
     * Currencies Meta counts in whole units; every other one in hundredths.
     */
    private const WHOLE_UNIT_CURRENCIES = ['CLP', 'COP', 'CRC', 'HUF', 'ISK', 'IDR', 'JPY', 'KRW', 'PYG', 'TWD', 'VND'];

    /**
     * What counts as a conversion: someone signing up. A purchase usually
     * follows a sign-up, so counting both would count one person twice.
     */
    private const CONVERSION_ACTIONS = ['complete_registration', 'lead'];

    public function __construct(private AdAccessTokens $tokens) {}

    public function capabilities(): AdCapabilities
    {
        // ponytail: one headline and one primary text per ad, a variant is
        // another ad. Meta's asset_feed_spec takes five of each, at the cost
        // of dynamic creative ad sets that hold a single ad.
        return new AdCapabilities(
            minHeadlines: 1,
            maxHeadlines: 1,
            headlineMaxLength: 40,
            minDescriptions: 1,
            maxDescriptions: 1,
            descriptionMaxLength: 125,
            targeting: ['countries'],
            identity: 'Facebook Page',
        );
    }

    public function authorizationUrl(PlatformApp $app, string $redirectUri, string $state): string
    {
        return 'https://www.facebook.com/v23.0/dialog/oauth?'.http_build_query([
            'client_id' => $app->client_id,
            'redirect_uri' => $redirectUri,
            'response_type' => 'code',
            'scope' => self::SCOPES,
            'state' => $state,
        ]);
    }

    public function exchangeCode(PlatformApp $app, string $code, string $redirectUri): AdTokens
    {
        $response = Http::get(self::API.'/oauth/access_token', [
            'client_id' => $app->client_id,
            'client_secret' => $app->client_secret,
            'redirect_uri' => $redirectUri,
            'code' => $code,
        ]);
        $this->throwUnless($response);

        return $this->refreshTokens($app, (string) $response->json('access_token'));
    }

    /**
     * Meta has no refresh token: a valid token is traded for a 60-day one,
     * so the token doubles as its own refresh token.
     */
    public function refreshTokens(PlatformApp $app, string $refreshToken): AdTokens
    {
        $response = Http::get(self::API.'/oauth/access_token', [
            'grant_type' => 'fb_exchange_token',
            'client_id' => $app->client_id,
            'client_secret' => $app->client_secret,
            'fb_exchange_token' => $refreshToken,
        ]);

        if ($response->json('error.code') === 190) {
            throw AdPlatformException::revoked('Meta no longer accepts this connection. Connect the account again.');
        }

        $this->throwUnless($response);

        $token = (string) $response->json('access_token');

        return new AdTokens(
            accessToken: $token,
            refreshToken: $token,
            expiresAt: CarbonImmutable::now()->addSeconds((int) $response->json('expires_in', 5_184_000)),
        );
    }

    public function accounts(PlatformApp $app, AdTokens $tokens): Collection
    {
        $request = Http::withToken($tokens->accessToken)->acceptJson();

        // Pages belong to the person, not to an ad account: every account
        // offers every Page, and a person picks one per account.
        $pages = array_values($this->paged($request, self::API.'/me/accounts', ['fields' => 'id,name', 'limit' => 100])
            ->map(fn (array $page): array => ['id' => (string) $page['id'], 'name' => (string) ($page['name'] ?? $page['id'])])
            ->all());

        return $this->paged($request, self::API.'/me/adaccounts', ['fields' => 'account_id,name,currency,timezone_name', 'limit' => 100])
            ->map(fn (array $account): AdAccountData => new AdAccountData(
                externalId: (string) $account['account_id'],
                name: (string) ($account['name'] ?? $account['account_id']),
                currency: (string) $account['currency'],
                timeZone: $account['timezone_name'] ?? null,
                identities: $pages,
            ))
            ->values();
    }

    public function hasConversionTracking(AdAccount $account): bool
    {
        $response = $this->request($account)->get(self::API."/act_{$account->external_id}/adspixels", ['fields' => 'id', 'limit' => 1]);
        $this->throwUnless($response);

        return $response->json('data') !== [];
    }

    public function createCampaign(AdCampaign $campaign): LaunchedCampaign
    {
        $account = $campaign->account;
        $countries = array_map('strtoupper', $campaign->targeting['countries'] ?? []);

        if ($account->identity_id === null) {
            throw new AdPlatformException('Meta ads are posted as a Facebook Page: pick one for this account on the Ads page.');
        }

        if ($countries === []) {
            throw new AdPlatformException('Meta needs at least one country to show the ads in.');
        }

        $remote = $this->post($account, "act_{$account->external_id}/campaigns", [
            // Names are not unique on our side.
            'name' => "{$campaign->name} #{$campaign->id}",
            'objective' => 'OUTCOME_TRAFFIC',
            'status' => 'PAUSED',
            'special_ad_categories' => [],
            'daily_budget' => $this->minorUnits($account, $campaign->daily_budget_micros),
            'bid_strategy' => 'LOWEST_COST_WITHOUT_CAP',
        ]);

        try {
            $adSet = $this->post($account, "act_{$account->external_id}/adsets", [
                'name' => "{$campaign->name} #{$campaign->id}",
                'campaign_id' => $remote,
                'status' => 'ACTIVE',
                'billing_event' => 'IMPRESSIONS',
                'optimization_goal' => 'LINK_CLICKS',
                'destination_type' => 'WEBSITE',
                'targeting' => [
                    'geo_locations' => ['countries' => $countries],
                    'targeting_automation' => ['advantage_audience' => 1],
                ],
                // The EU's Digital Services Act wants both named on every ad set.
                'dsa_beneficiary' => $campaign->project->name,
                'dsa_payor' => $campaign->project->name,
            ]);

            $ads = $campaign->ads()->where('status', AdStatus::Draft)->with('creative')->get();

            $adExternalIds = $ads->mapWithKeys(fn (Ad $ad): array => [
                $ad->id => $this->postAd($account, $adSet, $ad),
            ])->all();
        } catch (AdPlatformException $exception) {
            $this->request($account)->delete(self::API."/{$remote}");

            throw $exception;
        }

        return new LaunchedCampaign(
            externalId: $remote,
            ref: ['ad_set' => $adSet],
            adExternalIds: $adExternalIds,
        );
    }

    public function createAd(Ad $ad): string
    {
        $campaign = $ad->campaign;

        return $this->postAd($campaign->account, $campaign->external_ref['ad_set'], $ad);
    }

    public function setCampaignStatus(AdCampaign $campaign, AdStatus $status): void
    {
        $this->post($campaign->account, (string) $campaign->external_id, ['status' => $this->status($status)]);
    }

    public function setAdStatus(Ad $ad, AdStatus $status): void
    {
        $this->post($ad->campaign->account, (string) $ad->external_id, ['status' => $this->status($status)]);
    }

    public function setDailyBudget(AdCampaign $campaign, int $micros): void
    {
        $this->post($campaign->account, (string) $campaign->external_id, ['daily_budget' => $this->minorUnits($campaign->account, $micros)]);
    }

    public function metrics(AdAccount $account, CarbonImmutable $from, CarbonImmutable $to): Collection
    {
        $rows = $this->paged($this->request($account), self::API."/act_{$account->external_id}/insights", [
            'level' => 'ad',
            'fields' => 'ad_id,impressions,inline_link_clicks,spend,actions,action_values',
            'time_increment' => 1,
            'time_range' => json_encode(['since' => $from->toDateString(), 'until' => $to->toDateString()]),
            'limit' => 500,
        ]);

        // Numbers arrive as strings, spend in currency units.
        return $rows->map(fn (array $row): AdMetricRow => new AdMetricRow(
            adExternalId: (string) $row['ad_id'],
            date: CarbonImmutable::parse($row['date_start']),
            impressions: (int) ($row['impressions'] ?? 0),
            clicks: (int) ($row['inline_link_clicks'] ?? 0),
            costMicros: (int) round((float) ($row['spend'] ?? 0) * 1_000_000),
            conversions: $this->sumActions($row['actions'] ?? []),
            conversionValue: $this->sumActions($row['action_values'] ?? []),
        ));
    }

    /**
     * A creative, then the ad wearing it. Returns the ad's id.
     */
    private function postAd(AdAccount $account, string $adSet, Ad $ad): string
    {
        $creative = $this->post($account, "act_{$account->external_id}/adcreatives", [
            'name' => "Eveil ad #{$ad->id}",
            'object_story_spec' => [
                'page_id' => $account->identity_id,
                'link_data' => [
                    'link' => $ad->creative->final_url,
                    'name' => $ad->creative->headlines[0],
                    'message' => $ad->creative->descriptions[0],
                    'call_to_action' => ['type' => 'LEARN_MORE'],
                ],
            ],
        ]);

        return $this->post($account, "act_{$account->external_id}/ads", [
            'name' => "Eveil ad #{$ad->id}",
            'adset_id' => $adSet,
            'creative' => ['creative_id' => $creative],
            'status' => 'ACTIVE',
        ]);
    }

    /**
     * Creates or updates a node. Returns the id Meta answers with, the
     * node's own on an update.
     *
     * @param  array<string, mixed>  $fields
     */
    private function post(AdAccount $account, string $path, array $fields): string
    {
        $response = $this->request($account)->post(self::API."/{$path}", $fields);
        $this->throwUnless($response);

        return (string) ($response->json('id') ?? $path);
    }

    /**
     * Every row of a list, following Meta's `paging.next` links.
     *
     * @param  array<string, mixed>  $query
     * @return Collection<int, array<string, mixed>>
     */
    private function paged(PendingRequest $request, string $url, array $query): Collection
    {
        $rows = collect();

        do {
            $response = $request->get($url, $query);
            $this->throwUnless($response);

            $rows->push(...(array) $response->json('data', []));
            // The next link carries the whole query already.
            $url = $response->json('paging.next');
            $query = [];
        } while (is_string($url));

        return $rows;
    }

    /**
     * @param  array<int, array{action_type?: string, value?: string}>  $actions
     */
    private function sumActions(array $actions): float
    {
        return (float) collect($actions)
            ->whereIn('action_type', self::CONVERSION_ACTIONS)
            ->sum(fn (array $action): float => (float) ($action['value'] ?? 0));
    }

    private function minorUnits(AdAccount $account, int $micros): int
    {
        return (int) round($micros / (in_array($account->currency, self::WHOLE_UNIT_CURRENCIES, true) ? 1_000_000 : 10_000));
    }

    private function status(AdStatus $status): string
    {
        return match ($status) {
            AdStatus::Active => 'ACTIVE',
            AdStatus::Removed => 'DELETED',
            default => 'PAUSED',
        };
    }

    private function request(AdAccount $account): PendingRequest
    {
        return Http::withToken($this->tokens->for($account))->acceptJson();
    }

    /**
     * Meta's `error_user_msg` is written for a person; `message` is the
     * fallback, often "Invalid parameter".
     */
    private function throwUnless(Response $response): void
    {
        if ($response->successful()) {
            return;
        }

        $message = $response->json('error.error_user_msg') ?? $response->json('error.message') ?? "HTTP {$response->status()}";

        // 190: the token is expired or revoked. 200 and up: a permission is missing.
        $code = (int) $response->json('error.code');

        if ($code === 190 || ($code >= 200 && $code < 300) || $response->status() === 401) {
            throw AdPlatformException::revoked("Meta refused access: {$message}");
        }

        throw new AdPlatformException("Meta: {$message}");
    }
}
