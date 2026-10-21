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
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Sleep;
use Locale;
use Throwable;
use ZipArchive;

/**
 * Microsoft Advertising over its REST API, no SDK: every operation is a JSON
 * call mirroring the SOAP one it replaces.
 *
 * Search campaigns with responsive search ads only, the same text creative
 * Google takes, with the same limits. Unlike Google, Microsoft still wants a
 * developer token on every call (stored on the `platform_apps` row), and the
 * customer that owns the account (kept in the account's `external_ref`).
 *
 * A campaign is built in several calls, since Microsoft has no atomic
 * mutate: anything refused after the campaign exists deletes it again, so a
 * refusal halfway leaves nothing behind on the account.
 */
class MicrosoftAdsClient implements AdClientInterface
{
    private const CAMPAIGN_API = 'https://campaign.api.bingads.microsoft.com/CampaignManagement/v13';

    private const CUSTOMER_API = 'https://clientcenter.api.bingads.microsoft.com/CustomerManagement/v13';

    private const REPORTING_API = 'https://reporting.api.bingads.microsoft.com/Reporting/v13';

    private const OAUTH = 'https://login.microsoftonline.com/common/oauth2/v2.0';

    private const SCOPE = 'https://ads.microsoft.com/msads.manage offline_access';

    /**
     * The ad languages a search campaign accepts, by their English name.
     */
    private const LANGUAGES = [
        'Albanian', 'Bulgarian', 'Croatian', 'Czech', 'Danish', 'Dutch', 'English', 'Estonian', 'Finnish', 'French',
        'German', 'Greek', 'Hungarian', 'Italian', 'Japanese', 'Latvian', 'Lithuanian', 'Maltese', 'Norwegian',
        'Polish', 'Portuguese', 'Romanian', 'Slovak', 'Slovenian', 'Spanish', 'Swedish',
    ];

    /**
     * Error codes that mean the grant itself is gone: invalid credentials,
     * user not authorized, token expired.
     */
    private const REVOKED_CODES = [105, 106, 109];

    /**
     * A report is polled every few seconds for up to two minutes.
     */
    private const REPORT_POLLS = 24;

    public function __construct(private AdAccessTokens $tokens) {}

    public function capabilities(): AdCapabilities
    {
        // Responsive search ad limits, identical to Google's.
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
        return self::OAUTH.'/authorize?'.http_build_query([
            'client_id' => $app->client_id,
            'redirect_uri' => $redirectUri,
            'response_type' => 'code',
            'response_mode' => 'query',
            // offline_access is what makes Microsoft hand out a refresh token.
            'scope' => self::SCOPE,
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

    public function accounts(PlatformApp $app, AdTokens $tokens): Collection
    {
        if ($app->developer_token === null || $app->developer_token === '') {
            throw new AdPlatformException('Microsoft Advertising needs a developer token. Add it to the Microsoft Advertising app in Settings, Ad platforms.');
        }

        $request = Http::withToken($tokens->accessToken)->withHeaders(['DeveloperToken' => $app->developer_token])->acceptJson();

        // No user id: the one the token belongs to.
        $user = $request->post(self::CUSTOMER_API.'/User/Query', ['UserId' => null]);
        $this->throwUnless($user);

        $response = $request->post(self::CUSTOMER_API.'/Accounts/Search', [
            'Predicates' => [['Field' => 'UserId', 'Operator' => 'Equals', 'Value' => (string) $user->json('User.Id')]],
            'PageInfo' => ['Index' => 0, 'Size' => 1000],
        ]);
        $this->throwUnless($response);

        // ponytail: the owning customer is the one every later call names, so
        // an account reached only through an agency's customer is called as
        // its owner. Storing the agency's customer id instead lifts that.
        return collect((array) $response->json('Accounts', []))
            ->map(fn (array $account): AdAccountData => new AdAccountData(
                externalId: (string) $account['Id'],
                name: (string) ($account['Name'] ?? $account['Id']),
                currency: (string) $account['CurrencyCode'],
                // ponytail: Microsoft names its own time zones
                // ("BrusselsCopenhagenMadridParis"), so the account's day is
                // the app's until they are mapped to IANA ones.
                timeZone: null,
                ref: array_filter([
                    'customer_id' => (string) ($account['ParentCustomerId'] ?? ''),
                    'time_zone' => (string) ($account['TimeZone'] ?? ''),
                ]),
            ))
            ->values();
    }

    public function hasConversionTracking(AdAccount $account): bool
    {
        $response = $this->request($account)->post(self::CAMPAIGN_API.'/ConversionGoals/QueryByIds', [
            'ConversionGoalIds' => null,
            'ConversionGoalTypes' => 'Url,Duration,PagesViewedPerVisit,Event,AppInstall,OfflineConversion,InStoreTransaction',
        ]);
        $this->throwUnless($response);

        return collect((array) $response->json('ConversionGoals', []))
            ->contains(fn (?array $goal): bool => ($goal['Status'] ?? null) === 'Active');
    }

    public function createCampaign(AdCampaign $campaign): LaunchedCampaign
    {
        $account = $campaign->account;
        $targeting = $campaign->targeting;

        $response = $this->request($account)->post(self::CAMPAIGN_API.'/Campaigns', [
            'AccountId' => $account->external_id,
            'Campaigns' => [[
                // Names are unique per account on Microsoft, ours are not.
                'Name' => "{$campaign->name} #{$campaign->id}",
                'CampaignType' => 'Search',
                'BudgetType' => 'DailyBudgetStandard',
                'DailyBudget' => $campaign->daily_budget_micros / 1_000_000,
                'Status' => 'Paused',
                'TimeZone' => $account->external_ref['time_zone'] ?? 'PacificTimeUSCanadaTijuana',
                'Languages' => $this->languages($targeting['languages'] ?? []),
                // Without conversions to aim at, maximize clicks instead.
                'BiddingScheme' => ['Type' => $account->has_conversion_tracking ? 'MaxConversions' : 'MaxClicks'],
            ]],
        ]);
        $remote = $this->ids($response, 'CampaignIds')[0];

        try {
            return $this->fillCampaign($campaign, $remote, $targeting);
        } catch (Throwable $e) {
            // Best effort: the refusal is what the user needs to read.
            rescue(fn () => $this->request($account)->delete(self::CAMPAIGN_API.'/Campaigns', [
                'AccountId' => $account->external_id,
                'CampaignIds' => [$remote],
            ]), report: false);

            throw $e;
        }
    }

    public function createAd(Ad $ad): string
    {
        $campaign = $ad->campaign;

        $response = $this->request($campaign->account)->post(self::CAMPAIGN_API.'/Ads', [
            'AdGroupId' => $campaign->external_ref['ad_group'],
            'Ads' => [$this->adBody($ad)],
        ]);

        return $this->ids($response, 'AdIds')[0];
    }

    public function setCampaignStatus(AdCampaign $campaign, AdStatus $status): void
    {
        $account = $campaign->account;

        if ($status === AdStatus::Removed) {
            $response = $this->request($account)->delete(self::CAMPAIGN_API.'/Campaigns', [
                'AccountId' => $account->external_id,
                'CampaignIds' => [$campaign->external_id],
            ]);
        } else {
            $response = $this->request($account)->put(self::CAMPAIGN_API.'/Campaigns', [
                'AccountId' => $account->external_id,
                'Campaigns' => [['Id' => $campaign->external_id, 'Status' => $this->status($status)]],
            ]);
        }

        $this->throwUnlessComplete($response);
    }

    public function setAdStatus(Ad $ad, AdStatus $status): void
    {
        $campaign = $ad->campaign;
        $adGroup = $campaign->external_ref['ad_group'];

        if ($status === AdStatus::Removed) {
            $response = $this->request($campaign->account)->delete(self::CAMPAIGN_API.'/Ads', [
                'AdGroupId' => $adGroup,
                'AdIds' => [$ad->external_id],
            ]);
        } else {
            $response = $this->request($campaign->account)->put(self::CAMPAIGN_API.'/Ads', [
                'AdGroupId' => $adGroup,
                'Ads' => [['Type' => 'ResponsiveSearch', 'Id' => $ad->external_id, 'Status' => $this->status($status)]],
            ]);
        }

        $this->throwUnlessComplete($response);
    }

    public function setDailyBudget(AdCampaign $campaign, int $micros): void
    {
        $response = $this->request($campaign->account)->put(self::CAMPAIGN_API.'/Campaigns', [
            'AccountId' => $campaign->account->external_id,
            'Campaigns' => [['Id' => $campaign->external_id, 'DailyBudget' => $micros / 1_000_000]],
        ]);

        $this->throwUnlessComplete($response);
    }

    /**
     * Microsoft reports asynchronously: submit, poll until ready, download a
     * zipped CSV. All three happen here, inside the sync's job: an account
     * Eveil runs a handful of ads on is ready in seconds.
     */
    public function metrics(AdAccount $account, CarbonImmutable $from, CarbonImmutable $to): Collection
    {
        $submitted = $this->request($account)->post(self::REPORTING_API.'/GenerateReport/Submit', [
            'ReportRequest' => [
                'Type' => 'AdPerformanceReportRequest',
                'Format' => 'Csv',
                'ExcludeReportHeader' => true,
                'ExcludeReportFooter' => true,
                'ReturnOnlyCompleteData' => false,
                'Aggregation' => 'Daily',
                'Columns' => ['TimePeriod', 'AdId', 'Impressions', 'Clicks', 'Spend', 'Conversions', 'Revenue'],
                'Scope' => ['AccountIds' => [$account->external_id]],
                'Time' => [
                    'CustomDateRangeStart' => ['Day' => $from->day, 'Month' => $from->month, 'Year' => $from->year],
                    'CustomDateRangeEnd' => ['Day' => $to->day, 'Month' => $to->month, 'Year' => $to->year],
                ],
            ],
        ]);
        $this->throwUnless($submitted);

        $url = $this->reportUrl($account, (string) $submitted->json('ReportRequestId'));

        // Ready with no URL: nothing happened over the period.
        if ($url === null) {
            return collect();
        }

        return collect($this->reportRows(Http::get($url)->throw()->body()))->map(fn (array $row): AdMetricRow => new AdMetricRow(
            adExternalId: (string) $row['AdId'],
            date: CarbonImmutable::parse($row['TimePeriod']),
            impressions: (int) $this->number($row['Impressions'] ?? '0'),
            clicks: (int) $this->number($row['Clicks'] ?? '0'),
            costMicros: (int) round($this->number($row['Spend'] ?? '0') * 1_000_000),
            conversions: $this->number($row['Conversions'] ?? '0'),
            conversionValue: $this->number($row['Revenue'] ?? '0'),
        ));
    }

    /**
     * Everything below the campaign: its targeting, its one ad group, the
     * keywords and the draft ads.
     *
     * @param  array<string, mixed>  $targeting
     */
    private function fillCampaign(AdCampaign $campaign, string $remote, array $targeting): LaunchedCampaign
    {
        $account = $campaign->account;
        $locations = $this->locationIds($account, $targeting['countries'] ?? []);

        if ($locations !== []) {
            $this->throwUnlessComplete($this->request($account)->post(self::CAMPAIGN_API.'/CampaignCriterions', [
                'CriterionType' => 'Targets',
                'CampaignCriterions' => array_map(fn (string $location): array => [
                    'Type' => 'BiddableCampaignCriterion',
                    'CampaignId' => $remote,
                    'Criterion' => ['Type' => 'LocationCriterion', 'LocationId' => $location],
                    'CriterionBid' => ['Type' => 'BidMultiplier', 'Multiplier' => 0],
                ], $locations),
            ]));
        }

        if (($targeting['negative_keywords'] ?? []) !== []) {
            $this->throwUnlessComplete($this->request($account)->post(self::CAMPAIGN_API.'/EntityNegativeKeywords', [
                'EntityNegativeKeywords' => [[
                    'EntityId' => $remote,
                    'EntityType' => 'Campaign',
                    'NegativeKeywords' => array_map(fn (string $keyword): array => ['Text' => $keyword, 'MatchType' => 'Phrase'], $targeting['negative_keywords']),
                ]],
            ]));
        }

        $adGroup = $this->ids($this->request($account)->post(self::CAMPAIGN_API.'/AdGroups', [
            'CampaignId' => $remote,
            'AdGroups' => [[
                'Name' => "{$campaign->name} #{$campaign->id}",
                'Status' => 'Active',
                // Microsoft's own search results only, no syndication partners.
                'Network' => 'OwnedAndOperatedOnly',
            ]],
        ]), 'AdGroupIds')[0];

        if (($targeting['keywords'] ?? []) !== []) {
            $this->ids($this->request($account)->post(self::CAMPAIGN_API.'/Keywords', [
                'AdGroupId' => $adGroup,
                'Keywords' => array_map(fn (string $keyword): array => ['Text' => $keyword, 'MatchType' => 'Phrase', 'Status' => 'Active'], $targeting['keywords']),
            ]), 'KeywordIds');
        }

        $ads = $campaign->ads()->where('status', AdStatus::Draft)->with('creative')->get()->values();
        $adIds = $ads->isEmpty() ? [] : $this->ids($this->request($account)->post(self::CAMPAIGN_API.'/Ads', [
            'AdGroupId' => $adGroup,
            'Ads' => $ads->map(fn (Ad $ad): array => $this->adBody($ad))->all(),
        ]), 'AdIds');

        return new LaunchedCampaign(
            externalId: $remote,
            ref: ['ad_group' => $adGroup],
            // Ids come back in the order the ads went out.
            adExternalIds: $ads->mapWithKeys(fn (Ad $ad, int $index): array => [$ad->id => $adIds[$index]])->all(),
        );
    }

    /**
     * @param  array<string, string>  $grant
     */
    private function token(PlatformApp $app, array $grant): AdTokens
    {
        $response = Http::asForm()->post(self::OAUTH.'/token', [
            ...$grant,
            'client_id' => $app->client_id,
            'client_secret' => $app->client_secret,
            'scope' => self::SCOPE,
        ]);

        if ($response->json('error') === 'invalid_grant') {
            throw AdPlatformException::revoked('Microsoft no longer accepts this connection. Connect the account again.');
        }

        $this->throwUnless($response);

        // Microsoft rotates the refresh token on every use.
        return new AdTokens(
            accessToken: (string) $response->json('access_token'),
            refreshToken: $response->json('refresh_token'),
            expiresAt: CarbonImmutable::now()->addSeconds((int) $response->json('expires_in', 3600)),
        );
    }

    /**
     * The URL of a submitted report once it is ready, or null when it holds
     * no rows.
     */
    private function reportUrl(AdAccount $account, string $reportId): ?string
    {
        for ($poll = 0; $poll < self::REPORT_POLLS; $poll++) {
            $response = $this->request($account)->post(self::REPORTING_API.'/GenerateReport/Poll', ['ReportRequestId' => $reportId]);
            $this->throwUnless($response);

            $status = $response->json('ReportRequestStatus.Status');

            if ($status === 'Success') {
                return $response->json('ReportRequestStatus.ReportDownloadUrl');
            }

            if ($status === 'Error') {
                throw new AdPlatformException('Microsoft Advertising could not build the performance report.');
            }

            Sleep::for(5)->seconds();
        }

        throw new AdPlatformException('Microsoft Advertising took too long to build the performance report. The next sync asks again.');
    }

    /**
     * The rows of a zipped CSV report, keyed by column name.
     *
     * @return list<array<string, string>>
     */
    private function reportRows(string $zip): array
    {
        $path = tempnam(sys_get_temp_dir(), 'msads');
        file_put_contents($path, $zip);
        $archive = new ZipArchive;
        $csv = $archive->open($path) === true ? $archive->getFromIndex(0) : false;
        unlink($path);

        if ($csv === false) {
            throw new AdPlatformException('Microsoft Advertising sent a report that could not be opened.');
        }

        // Strip the byte order mark Microsoft puts in front.
        $lines = array_values(array_filter(preg_split('/\r\n|\n/', ltrim($csv, "\u{FEFF}")) ?: [], fn (string $line): bool => trim($line) !== ''));

        if ($lines === []) {
            return [];
        }

        $columns = str_getcsv(array_shift($lines), escape: '');

        return array_map(fn (string $line): array => array_combine($columns, array_pad(str_getcsv($line, escape: ''), count($columns), '')), $lines);
    }

    /**
     * Microsoft location ids for country codes, read from its geographical
     * locations file (it has no country code column, so countries match on
     * their English name). Unknown codes are dropped, as on Google.
     *
     * @param  list<string>  $codes
     * @return array<int, string>
     */
    private function locationIds(AdAccount $account, array $codes): array
    {
        if ($codes === []) {
            return [];
        }

        $countries = Cache::remember('microsoft-ads:countries', now()->addMonth(), fn (): array => $this->countries($account));

        return collect($codes)
            ->map(fn (string $code): string => mb_strtolower((string) Locale::getDisplayRegion('-'.strtoupper($code), 'en')))
            ->map(fn (string $name): ?string => $countries[$name] ?? null)
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    /**
     * Every active country in the locations file, by lowercased name. The
     * file lists every city and postal code too, so it is streamed rather
     * than read whole.
     *
     * @return array<string, string>
     */
    private function countries(AdAccount $account): array
    {
        $response = $this->request($account)->post(self::CAMPAIGN_API.'/GeoLocationsFileUrl/Query', [
            'Version' => '2.0',
            'LanguageLocale' => 'en',
            'CompressionType' => 'GZip',
        ]);
        $this->throwUnless($response);

        $path = tempnam(sys_get_temp_dir(), 'msgeo');
        $countries = [];

        try {
            Http::sink($path)->get((string) $response->json('FileUrl'))->throw();
            $file = fopen("compress.zlib://{$path}", 'r');

            if ($file === false) {
                throw new AdPlatformException('Microsoft Advertising sent a locations file that could not be opened.');
            }

            $columns = array_map(fn (?string $column): string => ltrim((string) $column, "\u{FEFF}"), fgetcsv($file, escape: '') ?: []);

            while (($line = fgetcsv($file, escape: '')) !== false) {
                $row = array_combine($columns, array_pad($line, count($columns), ''));

                if ($row['Location Type'] === 'Country' && $row['Status'] === 'Active') {
                    $countries[mb_strtolower($row['Bing Display Name'])] = $row['Location Id'];
                }
            }

            fclose($file);
        } finally {
            unlink($path);
        }

        return $countries;
    }

    /**
     * Language codes as Microsoft names them, or every language when none of
     * them is one Microsoft serves.
     *
     * @param  list<string>  $codes
     * @return array<int, string>
     */
    private function languages(array $codes): array
    {
        $names = collect($codes)
            ->map(fn (string $code): string => (string) Locale::getDisplayLanguage(strtolower($code), 'en'))
            ->filter(fn (string $name): bool => in_array($name, self::LANGUAGES, true))
            ->unique()
            ->values()
            ->all();

        return $names === [] ? ['All'] : $names;
    }

    /**
     * @return array<string, mixed>
     */
    private function adBody(Ad $ad): array
    {
        $assets = fn (array $texts): array => array_map(fn (string $text): array => ['Asset' => ['Type' => 'TextAsset', 'Text' => $text]], $texts);

        return [
            'Type' => 'ResponsiveSearch',
            'FinalUrls' => [$ad->creative->final_url],
            'Headlines' => $assets($ad->creative->headlines),
            'Descriptions' => $assets($ad->creative->descriptions),
            'Status' => 'Active',
        ];
    }

    private function status(AdStatus $status): string
    {
        return $status === AdStatus::Active ? 'Active' : 'Paused';
    }

    private function number(string $value): float
    {
        return (float) str_replace(',', '', $value);
    }

    private function request(AdAccount $account): PendingRequest
    {
        return Http::withToken($this->tokens->for($account))
            ->withHeaders([
                'DeveloperToken' => (string) $account->platformApp?->developer_token,
                'CustomerId' => (string) ($account->external_ref['customer_id'] ?? ''),
                'CustomerAccountId' => $account->external_id,
            ])
            ->acceptJson();
    }

    /**
     * The ids a batch call created, in the order sent. A batch answers 200
     * even when it refused items, listing them in `PartialErrors`.
     *
     * @return list<string>
     */
    private function ids(Response $response, string $key): array
    {
        $this->throwUnlessComplete($response);

        $ids = (array) $response->json($key, []);

        if ($ids === [] || in_array(null, $ids, true)) {
            throw new AdPlatformException('Microsoft Advertising did not create everything it was sent.');
        }

        return array_values(array_map('strval', $ids));
    }

    private function throwUnlessComplete(Response $response): void
    {
        $this->throwUnless($response);

        $error = $response->json('PartialErrors.0') ?? $response->json('NestedPartialErrors.0.BatchErrors.0');

        if (is_array($error)) {
            throw new AdPlatformException('Microsoft Advertising: '.($error['Message'] ?? $error['ErrorCode'] ?? 'refused part of the request.'));
        }
    }

    /**
     * The fault is the body itself, its useful sentence under one of three
     * lists depending on the kind of fault.
     */
    private function throwUnless(Response $response): void
    {
        if ($response->successful()) {
            return;
        }

        $fault = $response->json('ApplicationFault') ?? $response->json() ?? [];
        $error = data_get($fault, 'OperationErrors.0') ?? data_get($fault, 'Errors.0') ?? data_get($fault, 'BatchErrors.0') ?? [];
        $message = $error['Message'] ?? $response->json('error_description') ?? "HTTP {$response->status()}";

        if (in_array($response->status(), [401, 403], true) || in_array((int) ($error['Code'] ?? 0), self::REVOKED_CODES, true)) {
            throw AdPlatformException::revoked("Microsoft Advertising refused access: {$message}");
        }

        throw new AdPlatformException("Microsoft Advertising: {$message}");
    }
}
