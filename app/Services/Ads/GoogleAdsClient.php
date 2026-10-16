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
 * Google Ads over its REST API, no SDK: Google's PHP library pulls in gRPC and
 * protobuf for what is a handful of JSON calls here.
 *
 * Search campaigns with responsive search ads only, which is what a text
 * creative maps to. No developer token: Google retired them in September 2026,
 * access now belongs to the Cloud project behind the OAuth client.
 *
 * Every campaign is created in one atomic `googleAds:mutate` with temporary
 * negative ids, so a refusal halfway leaves nothing behind on the account.
 */
class GoogleAdsClient implements AdClientInterface
{
    private const API = 'https://googleads.googleapis.com/v25';

    private const SCOPE = 'https://www.googleapis.com/auth/adwords';

    public function __construct(private AdAccessTokens $tokens) {}

    public function capabilities(): AdCapabilities
    {
        // Responsive search ad limits.
        return new AdCapabilities(
            minHeadlines: 3,
            maxHeadlines: 15,
            headlineMaxLength: 30,
            minDescriptions: 2,
            maxDescriptions: 4,
            descriptionMaxLength: 90,
            targeting: ['keywords', 'negative_keywords', 'countries', 'languages'],
        );
    }

    public function authorizationUrl(PlatformApp $app, string $redirectUri, string $state): string
    {
        return 'https://accounts.google.com/o/oauth2/v2/auth?'.http_build_query([
            'client_id' => $app->client_id,
            'redirect_uri' => $redirectUri,
            'response_type' => 'code',
            'scope' => self::SCOPE,
            // Offline plus consent: the only way Google hands out a refresh
            // token every time, not just on the very first grant.
            'access_type' => 'offline',
            'prompt' => 'consent',
            'state' => $state,
        ]);
    }

    public function exchangeCode(PlatformApp $app, string $code, string $redirectUri): AdTokens
    {
        return $this->token($app, [
            'grant_type' => 'authorization_code',
            'code' => $code,
            'redirect_uri' => $redirectUri,
        ]);
    }

    public function refreshTokens(PlatformApp $app, string $refreshToken): AdTokens
    {
        return $this->token($app, [
            'grant_type' => 'refresh_token',
            'refresh_token' => $refreshToken,
        ]);
    }

    public function accounts(AdTokens $tokens): Collection
    {
        $response = Http::withToken($tokens->accessToken)->get(self::API.'/customers:listAccessibleCustomers');
        $this->throwUnless($response);

        return collect((array) $response->json('resourceNames'))
            ->map(fn (string $resourceName): string => str($resourceName)->afterLast('/')->value())
            ->map(function (string $customerId) use ($tokens): ?AdAccountData {
                $response = Http::withToken($tokens->accessToken)->post(self::API."/customers/{$customerId}/googleAds:search", [
                    'query' => 'SELECT customer.id, customer.descriptive_name, customer.currency_code, customer.time_zone, customer.manager FROM customer',
                ]);

                $customer = $response->json('results.0.customer');

                // ponytail: manager accounts are skipped, so a client account
                // reached only through one (agency setup) is not listed.
                // Storing a login-customer-id per account lifts that.
                if (! $response->successful() || ! is_array($customer) || ($customer['manager'] ?? false)) {
                    return null;
                }

                return new AdAccountData(
                    externalId: $customerId,
                    name: (string) ($customer['descriptiveName'] ?? $customerId),
                    currency: (string) $customer['currencyCode'],
                    timeZone: $customer['timeZone'] ?? null,
                );
            })
            ->filter()
            ->values();
    }

    public function hasConversionTracking(AdAccount $account): bool
    {
        return $this->search($account, "SELECT conversion_action.id FROM conversion_action WHERE conversion_action.status = 'ENABLED' LIMIT 1")->isNotEmpty();
    }

    public function createCampaign(AdCampaign $campaign): LaunchedCampaign
    {
        $account = $campaign->account;
        $customer = "customers/{$account->external_id}";
        $targeting = $campaign->targeting;

        $budget = "{$customer}/campaignBudgets/-1";
        $remote = "{$customer}/campaigns/-2";
        $adGroup = "{$customer}/adGroups/-3";

        $operations = [
            ['campaignBudgetOperation' => ['create' => [
                'resourceName' => $budget,
                // Names are unique per account on Google, ours are not.
                'name' => "{$campaign->name} #{$campaign->id}",
                'amountMicros' => (string) $campaign->daily_budget_micros,
                'deliveryMethod' => 'STANDARD',
                'explicitlyShared' => false,
            ]]],
            ['campaignOperation' => ['create' => [
                'resourceName' => $remote,
                'name' => "{$campaign->name} #{$campaign->id}",
                'status' => 'PAUSED',
                'advertisingChannelType' => 'SEARCH',
                'campaignBudget' => $budget,
                // Without conversions to aim at, maximize clicks instead.
                ...($account->has_conversion_tracking ? ['maximizeConversions' => (object) []] : ['targetSpend' => (object) []]),
                'networkSettings' => [
                    'targetGoogleSearch' => true,
                    'targetSearchNetwork' => false,
                    'targetContentNetwork' => false,
                    'targetPartnerSearchNetwork' => false,
                ],
                'containsEuPoliticalAdvertising' => 'DOES_NOT_CONTAIN_EU_POLITICAL_ADVERTISING',
            ]]],
        ];

        foreach ($this->constants($account, 'geo_target_constant', 'country_code', array_map('strtoupper', $targeting['countries'] ?? []), " AND geo_target_constant.target_type = 'Country'") as $geo) {
            $operations[] = ['campaignCriterionOperation' => ['create' => ['campaign' => $remote, 'location' => ['geoTargetConstant' => $geo]]]];
        }

        foreach ($this->constants($account, 'language_constant', 'code', array_map('strtolower', $targeting['languages'] ?? [])) as $language) {
            $operations[] = ['campaignCriterionOperation' => ['create' => ['campaign' => $remote, 'language' => ['languageConstant' => $language]]]];
        }

        foreach ($targeting['negative_keywords'] ?? [] as $keyword) {
            $operations[] = ['campaignCriterionOperation' => ['create' => ['campaign' => $remote, 'negative' => true, 'keyword' => ['text' => $keyword, 'matchType' => 'PHRASE']]]];
        }

        $operations[] = ['adGroupOperation' => ['create' => [
            'resourceName' => $adGroup,
            'name' => "{$campaign->name} #{$campaign->id}",
            'campaign' => $remote,
            'status' => 'ENABLED',
            'type' => 'SEARCH_STANDARD',
        ]]];

        foreach ($targeting['keywords'] ?? [] as $keyword) {
            $operations[] = ['adGroupCriterionOperation' => ['create' => ['adGroup' => $adGroup, 'status' => 'ENABLED', 'keyword' => ['text' => $keyword, 'matchType' => 'PHRASE']]]];
        }

        $ads = $campaign->ads()->where('status', AdStatus::Draft)->with('creative')->get();

        foreach ($ads as $ad) {
            $operations[] = ['adGroupAdOperation' => ['create' => ['adGroup' => $adGroup, 'status' => 'ENABLED', 'ad' => $this->adBody($ad)]]];
        }

        $response = $this->request($account)->post(self::API."/{$customer}/googleAds:mutate", ['mutateOperations' => $operations]);
        $this->throwUnless($response);

        $results = collect((array) $response->json('mutateOperationResponses'));
        $adResults = $results->pluck('adGroupAdResult.resourceName')->filter()->values();

        return new LaunchedCampaign(
            externalId: $this->idOf((string) $results->pluck('campaignResult.resourceName')->filter()->first()),
            ref: [
                'budget' => $this->idOf((string) $results->pluck('campaignBudgetResult.resourceName')->filter()->first()),
                'ad_group' => $this->idOf((string) $results->pluck('adGroupResult.resourceName')->filter()->first()),
            ],
            // Results come back in the order the operations went out.
            adExternalIds: $ads->values()->mapWithKeys(fn (Ad $ad, int $index): array => [
                $ad->id => str((string) $adResults->get($index))->afterLast('~')->value(),
            ])->all(),
        );
    }

    public function createAd(Ad $ad): string
    {
        $campaign = $ad->campaign;
        $customer = "customers/{$campaign->account->external_id}";

        $response = $this->request($campaign->account)->post(self::API."/{$customer}/adGroupAds:mutate", [
            'operations' => [['create' => [
                'adGroup' => "{$customer}/adGroups/{$campaign->external_ref['ad_group']}",
                'status' => 'ENABLED',
                'ad' => $this->adBody($ad),
            ]]],
        ]);
        $this->throwUnless($response);

        return str((string) $response->json('results.0.resourceName'))->afterLast('~')->value();
    }

    public function setCampaignStatus(AdCampaign $campaign, AdStatus $status): void
    {
        $this->setStatus($campaign->account, 'campaigns', "customers/{$campaign->account->external_id}/campaigns/{$campaign->external_id}", $status);
    }

    public function setAdStatus(Ad $ad, AdStatus $status): void
    {
        $campaign = $ad->campaign;

        $this->setStatus($campaign->account, 'adGroupAds', "customers/{$campaign->account->external_id}/adGroupAds/{$campaign->external_ref['ad_group']}~{$ad->external_id}", $status);
    }

    public function setDailyBudget(AdCampaign $campaign, int $micros): void
    {
        $customer = "customers/{$campaign->account->external_id}";

        $response = $this->request($campaign->account)->post(self::API."/{$customer}/campaignBudgets:mutate", [
            'operations' => [[
                'update' => ['resourceName' => "{$customer}/campaignBudgets/{$campaign->external_ref['budget']}", 'amountMicros' => (string) $micros],
                'updateMask' => 'amount_micros',
            ]],
        ]);
        $this->throwUnless($response);
    }

    public function metrics(AdAccount $account, CarbonImmutable $from, CarbonImmutable $to): Collection
    {
        $query = 'SELECT ad_group_ad.ad.id, segments.date, metrics.impressions, metrics.clicks, metrics.cost_micros, metrics.conversions, metrics.conversions_value'
            ." FROM ad_group_ad WHERE segments.date BETWEEN '{$from->toDateString()}' AND '{$to->toDateString()}'";

        // int64 fields arrive as strings, and a zero is left out entirely.
        return $this->search($account, $query)->map(fn (array $row): AdMetricRow => new AdMetricRow(
            adExternalId: (string) $row['adGroupAd']['ad']['id'],
            date: CarbonImmutable::parse($row['segments']['date']),
            impressions: (int) ($row['metrics']['impressions'] ?? 0),
            clicks: (int) ($row['metrics']['clicks'] ?? 0),
            costMicros: (int) ($row['metrics']['costMicros'] ?? 0),
            conversions: (float) ($row['metrics']['conversions'] ?? 0),
            conversionValue: (float) ($row['metrics']['conversionsValue'] ?? 0),
        ));
    }

    /**
     * @param  array<string, string>  $grant
     */
    private function token(PlatformApp $app, array $grant): AdTokens
    {
        $response = Http::asForm()->post('https://oauth2.googleapis.com/token', [
            ...$grant,
            'client_id' => $app->client_id,
            'client_secret' => $app->client_secret,
        ]);

        if ($response->json('error') === 'invalid_grant') {
            throw AdPlatformException::revoked('Google no longer accepts this connection. Connect the account again.');
        }

        $this->throwUnless($response);

        return new AdTokens(
            accessToken: (string) $response->json('access_token'),
            refreshToken: $response->json('refresh_token'),
            expiresAt: CarbonImmutable::now()->addSeconds((int) $response->json('expires_in', 3600)),
        );
    }

    /**
     * Every row a GAQL query returns, across pages.
     *
     * @return Collection<int, array<string, mixed>>
     */
    private function search(AdAccount $account, string $query): Collection
    {
        $rows = collect();
        $pageToken = null;

        do {
            $response = $this->request($account)->post(self::API."/customers/{$account->external_id}/googleAds:search", array_filter([
                'query' => $query,
                'pageToken' => $pageToken,
            ]));
            $this->throwUnless($response);

            $rows->push(...(array) $response->json('results', []));
            $pageToken = $response->json('nextPageToken');
        } while ($pageToken !== null);

        return $rows;
    }

    /**
     * Resource names of geo or language constants matching the given codes.
     *
     * @param  list<string>  $codes
     * @return array<int, string>
     */
    private function constants(AdAccount $account, string $resource, string $field, array $codes, string $extra = ''): array
    {
        $codes = array_values(array_filter($codes, fn (string $code): bool => preg_match('/^[A-Za-z-]{2,7}$/', $code) === 1));

        if ($codes === []) {
            return [];
        }

        $in = implode(', ', array_map(fn (string $code): string => "'{$code}'", $codes));

        return $this->search($account, "SELECT {$resource}.resource_name FROM {$resource} WHERE {$resource}.{$field} IN ({$in}){$extra}")
            ->map(fn (array $row): string => (string) data_get($row, str($resource)->camel()->append('.resourceName')->value()))
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    private function adBody(Ad $ad): array
    {
        return [
            'finalUrls' => [$ad->creative->final_url],
            'responsiveSearchAd' => [
                'headlines' => array_map(fn (string $text): array => ['text' => $text], $ad->creative->headlines),
                'descriptions' => array_map(fn (string $text): array => ['text' => $text], $ad->creative->descriptions),
            ],
        ];
    }

    private function setStatus(AdAccount $account, string $collection, string $resourceName, AdStatus $status): void
    {
        $operation = $status === AdStatus::Removed
            ? ['remove' => $resourceName]
            : ['update' => ['resourceName' => $resourceName, 'status' => $status === AdStatus::Active ? 'ENABLED' : 'PAUSED'], 'updateMask' => 'status'];

        $response = $this->request($account)->post(self::API."/customers/{$account->external_id}/{$collection}:mutate", ['operations' => [$operation]]);
        $this->throwUnless($response);
    }

    private function request(AdAccount $account): PendingRequest
    {
        return Http::withToken($this->tokens->for($account))->acceptJson();
    }

    private function idOf(string $resourceName): string
    {
        return str($resourceName)->afterLast('/')->value();
    }

    /**
     * Google nests the useful sentence under `details`; the top-level message
     * is often just "Request contains an invalid argument."
     */
    private function throwUnless(Response $response): void
    {
        if ($response->successful()) {
            return;
        }

        $message = $response->json('error.details.0.errors.0.message') ?? $response->json('error.message') ?? $response->json('error_description') ?? "HTTP {$response->status()}";

        if (in_array($response->status(), [401, 403], true)) {
            throw AdPlatformException::revoked("Google Ads refused access: {$message}");
        }

        throw new AdPlatformException("Google Ads: {$message}");
    }
}
