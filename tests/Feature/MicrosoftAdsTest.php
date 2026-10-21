<?php

use App\Actions\SyncAdMetrics;
use App\Enums\AdAccountStatus;
use App\Enums\AdPlatform;
use App\Enums\PlatformAppKind;
use App\Models\Ad;
use App\Models\AdAccount;
use App\Models\AdCampaign;
use App\Models\AdMetric;
use App\Models\Organization;
use App\Models\PlatformApp;
use App\Models\Project;
use App\Models\User;
use App\Services\Ads\AdPlatformException;
use App\Services\Ads\AdTokens;
use App\Services\Ads\MicrosoftAdsClient;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Sleep;

const MSADS_CAMPAIGN = 'campaign.api.bingads.microsoft.com/CampaignManagement/v13';

/**
 * @param  array<string, mixed>  $attributes
 */
function microsoftAccount(array $attributes = []): AdAccount
{
    $app = PlatformApp::factory()->create(['platform' => PlatformAppKind::MicrosoftAds, 'developer_token' => 'dev-token']);

    return AdAccount::factory()->create([
        'platform' => AdPlatform::MicrosoftAds,
        'platform_app_id' => $app->id,
        'external_id' => '149',
        'external_ref' => ['customer_id' => '77', 'time_zone' => 'BrusselsCopenhagenMadridParis'],
        'time_zone' => null,
        ...$attributes,
    ]);
}

/**
 * A report the way Microsoft sends it: one CSV, zipped, with a byte order mark.
 */
function zippedReport(string $csv): string
{
    $path = tempnam(sys_get_temp_dir(), 'report');
    $zip = new ZipArchive;
    $zip->open($path, ZipArchive::OVERWRITE);
    $zip->addFromString('report.csv', "\u{FEFF}".$csv);
    $zip->close();
    $bytes = (string) file_get_contents($path);
    unlink($path);

    return $bytes;
}

it('connects every account the Microsoft user can manage, remembering its customer', function () {
    Http::preventStrayRequests();
    $user = User::factory()->create();
    $organization = Organization::factory()->create();
    $organization->users()->attach($user, ['role' => 'owner']);
    $project = Project::factory()->for($organization)->create();
    $app = PlatformApp::factory()->create(['platform' => PlatformAppKind::MicrosoftAds, 'developer_token' => 'dev-token']);

    Http::fake([
        'login.microsoftonline.com/common/oauth2/v2.0/token' => Http::response(['access_token' => 'at', 'refresh_token' => 'rt', 'expires_in' => 3600]),
        'clientcenter.api.bingads.microsoft.com/CustomerManagement/v13/User/Query' => Http::response(['User' => ['Id' => '5']]),
        'clientcenter.api.bingads.microsoft.com/CustomerManagement/v13/Accounts/Search' => Http::response(['Accounts' => [
            ['Id' => '149', 'Name' => 'Acme', 'CurrencyCode' => 'EUR', 'ParentCustomerId' => '77', 'TimeZone' => 'BrusselsCopenhagenMadridParis'],
        ]]),
    ]);

    $this->actingAs($user)
        ->withSession(['ads_oauth' => ['state' => 'the-state', 'project_id' => $project->id, 'platform_app_id' => $app->id]])
        ->get(route('oauth.ads.callback', ['platform' => 'microsoft_ads', 'code' => 'a-code', 'state' => 'the-state']))
        ->assertRedirect(route('ads.index', ['project' => $project]));

    expect(AdAccount::query()->where('project_id', $project->id)->sole())
        ->platform->toBe(AdPlatform::MicrosoftAds)
        ->external_id->toBe('149')
        ->external_ref->toEqual(['customer_id' => '77', 'time_zone' => 'BrusselsCopenhagenMadridParis'])
        ->refresh_token->toBe('rt')
        ->monthly_budget_micros->toBe(0);
    Http::assertSent(fn (Request $request): bool => str_ends_with($request->url(), 'Accounts/Search')
        && $request->header('DeveloperToken') === ['dev-token']
        && $request['Predicates'][0]['Value'] === '5');
});

it('reads every page of accounts a Microsoft user can manage', function () {
    Http::preventStrayRequests();
    $app = PlatformApp::factory()->create(['platform' => PlatformAppKind::MicrosoftAds, 'developer_token' => 'dev-token']);
    $account = fn (int $id): array => ['Id' => (string) $id, 'Name' => "Account {$id}", 'CurrencyCode' => 'EUR', 'ParentCustomerId' => '77'];

    Http::fake([
        'clientcenter.api.bingads.microsoft.com/CustomerManagement/v13/User/Query' => Http::response(['User' => ['Id' => '5']]),
        'clientcenter.api.bingads.microsoft.com/CustomerManagement/v13/Accounts/Search' => Http::sequence()
            ->push(['Accounts' => array_map($account, range(1, 1000))])
            ->push(['Accounts' => [$account(1001)]]),
    ]);

    $accounts = app(MicrosoftAdsClient::class)->accounts($app, new AdTokens('at', 'rt', null));

    expect($accounts)->toHaveCount(1001)
        ->and($accounts->last()->externalId)->toBe('1001');
    Http::assertSent(fn (Request $request): bool => str_ends_with($request->url(), 'Accounts/Search') && $request['PageInfo']['Index'] === 1);
});

it('refuses to connect Microsoft accounts until the app has a developer token', function () {
    Http::preventStrayRequests();
    $user = User::factory()->create();
    $organization = Organization::factory()->create();
    $organization->users()->attach($user, ['role' => 'owner']);
    $project = Project::factory()->for($organization)->create();
    $app = PlatformApp::factory()->create(['platform' => PlatformAppKind::MicrosoftAds]);

    Http::fake(['login.microsoftonline.com/*' => Http::response(['access_token' => 'at', 'refresh_token' => 'rt', 'expires_in' => 3600])]);

    $this->actingAs($user)
        ->withSession(['ads_oauth' => ['state' => 'the-state', 'project_id' => $project->id, 'platform_app_id' => $app->id]])
        ->get(route('oauth.ads.callback', ['platform' => 'microsoft_ads', 'code' => 'a-code', 'state' => 'the-state']))
        ->assertSessionHas('status', 'Microsoft Advertising needs a developer token. Add it to the Microsoft Advertising app in Settings, Ad platforms.');

    expect(AdAccount::query()->exists())->toBeFalse();
});

it('launches a campaign paused, with its targeting, one ad group and the draft ads', function () {
    Http::preventStrayRequests();
    $account = microsoftAccount();
    $campaign = AdCampaign::factory()->create([
        'ad_account_id' => $account->id,
        'targeting' => ['keywords' => ['cold email tool'], 'negative_keywords' => ['free'], 'countries' => ['FR'], 'languages' => ['fr']],
    ]);
    $ads = Ad::factory()->count(2)->create(['ad_campaign_id' => $campaign->id]);

    Http::fake([
        MSADS_CAMPAIGN.'/Campaigns' => Http::response(['CampaignIds' => ['555'], 'PartialErrors' => null]),
        MSADS_CAMPAIGN.'/GeoLocationsFileUrl/Query' => Http::response(['FileUrl' => 'https://download.example.com/geo.csv.gz']),
        'download.example.com/*' => Http::response(gzencode("Location Id,Bing Display Name,Location Type,Status\n66,France,Country,Active\n1234,Paris|France,City,Active\n")),
        MSADS_CAMPAIGN.'/CampaignCriterions' => Http::response(['CampaignCriterionIds' => ['9'], 'NestedPartialErrors' => null]),
        MSADS_CAMPAIGN.'/EntityNegativeKeywords' => Http::response(['NegativeKeywordIds' => [['Ids' => ['3']]], 'NestedPartialErrors' => null]),
        MSADS_CAMPAIGN.'/AdGroups' => Http::response(['AdGroupIds' => ['666'], 'PartialErrors' => null]),
        MSADS_CAMPAIGN.'/Keywords' => Http::response(['KeywordIds' => ['1'], 'PartialErrors' => null]),
        MSADS_CAMPAIGN.'/Ads' => Http::response(['AdIds' => ['111', '112'], 'PartialErrors' => null]),
    ]);

    $launched = app(MicrosoftAdsClient::class)->createCampaign($campaign);

    expect($launched->externalId)->toBe('555')
        ->and($launched->ref)->toBe(['ad_group' => '666'])
        ->and($launched->adExternalIds)->toBe([$ads[0]->id => '111', $ads[1]->id => '112']);

    Http::assertSent(fn (Request $request): bool => str_ends_with($request->url(), '/Campaigns')
        && $request->header('CustomerId') === ['77']
        && $request->header('CustomerAccountId') === ['149']
        && $request['Campaigns'][0]['Status'] === 'Paused'
        && $request['Campaigns'][0]['DailyBudget'] == 10
        && $request['Campaigns'][0]['Languages'] === ['French']
        && $request['Campaigns'][0]['TimeZone'] === 'BrusselsCopenhagenMadridParis');
    Http::assertSent(fn (Request $request): bool => str_ends_with($request->url(), '/CampaignCriterions')
        && $request['CampaignCriterions'][0]['Criterion']['LocationId'] === '66');
    Http::assertSent(fn (Request $request): bool => str_ends_with($request->url(), '/Ads')
        && count($request['Ads']) === 2
        && $request['Ads'][0]['Type'] === 'ResponsiveSearch');
});

it('deletes a half-built campaign when Microsoft refuses part of it', function () {
    Http::preventStrayRequests();
    $account = microsoftAccount();
    $campaign = AdCampaign::factory()->create(['ad_account_id' => $account->id, 'targeting' => ['keywords' => [], 'countries' => [], 'languages' => []]]);
    Ad::factory()->create(['ad_campaign_id' => $campaign->id]);

    Http::fake([
        MSADS_CAMPAIGN.'/Campaigns' => Http::response(['CampaignIds' => ['555'], 'PartialErrors' => null]),
        MSADS_CAMPAIGN.'/AdGroups' => Http::response(['AdGroupIds' => ['666'], 'PartialErrors' => null]),
        MSADS_CAMPAIGN.'/Ads' => Http::response(['AdIds' => [null], 'PartialErrors' => [['Index' => 0, 'Message' => 'The ad headline is too long.']]]),
    ]);

    expect(fn () => app(MicrosoftAdsClient::class)->createCampaign($campaign))
        ->toThrow(AdPlatformException::class, 'Microsoft Advertising: The ad headline is too long.');

    Http::assertSent(fn (Request $request): bool => $request->method() === 'DELETE'
        && str_ends_with($request->url(), '/Campaigns')
        && $request['CampaignIds'] === ['555']);
});

it('reads the zipped report it waited for, keeping only the ads Eveil created', function () {
    Http::preventStrayRequests();
    Sleep::fake();
    $account = microsoftAccount();
    $ad = Ad::factory()->active()->create(['ad_campaign_id' => AdCampaign::factory()->active()->create(['ad_account_id' => $account->id])->id, 'external_id' => '111']);
    $day = now()->subDay()->toDateString();

    Http::fake([
        'reporting.api.bingads.microsoft.com/Reporting/v13/GenerateReport/Submit' => Http::response(['ReportRequestId' => 'r1']),
        'reporting.api.bingads.microsoft.com/Reporting/v13/GenerateReport/Poll' => Http::sequence()
            ->push(['ReportRequestStatus' => ['Status' => 'Pending', 'ReportDownloadUrl' => null]])
            ->push(['ReportRequestStatus' => ['Status' => 'Success', 'ReportDownloadUrl' => 'https://download.example.com/report.zip']]),
        'download.example.com/*' => Http::response(zippedReport(
            "\"TimePeriod\",\"AdId\",\"Impressions\",\"Clicks\",\"Spend\",\"Conversions\",\"Revenue\"\r\n"
            ."\"{$day}\",\"111\",\"1,200\",\"30\",\"12.50\",\"1.50\",\"40.00\"\r\n"
            // Not ours: an ad the user runs by hand.
            ."\"{$day}\",\"999\",\"500\",\"80\",\"20.00\",\"0\",\"0\"\r\n"
        )),
        MSADS_CAMPAIGN.'/ConversionGoals/QueryByIds' => Http::response(['ConversionGoals' => [['Id' => '1', 'Status' => 'Active']]]),
    ]);

    app(SyncAdMetrics::class)->handle($account);

    expect(AdMetric::query()->sole())
        ->ad_id->toBe($ad->id)
        ->impressions->toBe(1200)
        ->clicks->toBe(30)
        ->cost_micros->toBe(12_500_000)
        ->conversions->toBe(1.5);
    expect($account->fresh())
        ->has_conversion_tracking->toBeTrue()
        ->last_error->toBeNull();
    Sleep::assertSleptTimes(1);
});

it('flags the account when Microsoft says the grant is gone', function () {
    Http::preventStrayRequests();
    $account = microsoftAccount();

    Http::fake(['reporting.api.bingads.microsoft.com/*' => Http::response([
        'Type' => 'AdApiFaultDetail',
        'TrackingId' => 'abc',
        'Errors' => [['Code' => 109, 'Message' => 'The OAuth access token has expired.']],
    ], 400)]);

    app(SyncAdMetrics::class)->handle($account);

    expect($account->fresh())
        ->status->toBe(AdAccountStatus::Error)
        ->last_error->toBe('Microsoft Advertising refused access: The OAuth access token has expired.');
});

it('requires a developer token the first time an organization adds its own Microsoft app, and keeps it on a blank edit', function () {
    $user = User::factory()->create();
    $organization = Organization::factory()->create();
    $organization->users()->attach($user, ['role' => 'owner']);
    Project::factory()->for($organization)->create();

    $this->actingAs($user)->putJson(route('settings.platform-apps.update', 'microsoft_ads'), ['client_id' => 'own-id', 'client_secret' => 'own-secret'])
        ->assertJsonValidationErrors(['developer_token' => 'The developer token field is required.']);

    $this->actingAs($user)->putJson(route('settings.platform-apps.update', 'microsoft_ads'), ['client_id' => 'own-id', 'client_secret' => 'own-secret', 'developer_token' => 'dev-token'])->assertRedirect();
    $this->actingAs($user)->putJson(route('settings.platform-apps.update', 'microsoft_ads'), ['client_id' => 'own-id', 'client_secret' => '', 'developer_token' => ''])->assertRedirect();

    expect(PlatformApp::query()->where('organization_id', $organization->id)->sole())
        ->client_secret->toBe('own-secret')
        ->developer_token->toBe('dev-token');
});
