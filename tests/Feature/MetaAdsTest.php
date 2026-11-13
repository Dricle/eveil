<?php

use App\Actions\ConnectAdAccounts;
use App\Actions\SyncAdMetrics;
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
use App\Services\Ads\MetaAdsClient;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

const META_GRAPH = 'graph.facebook.com/v23.0';

/**
 * @param  array<string, mixed>  $attributes
 */
function metaAccount(array $attributes = []): AdAccount
{
    $app = PlatformApp::factory()->create(['platform' => PlatformAppKind::MetaAds]);

    return AdAccount::factory()->create([
        'platform' => AdPlatform::MetaAds,
        'platform_app_id' => $app->id,
        'external_id' => '149',
        'identities' => [['id' => '42', 'name' => 'Acme'], ['id' => '43', 'name' => 'Acme Labs']],
        'identity_id' => '42',
        'currency' => 'EUR',
        ...$attributes,
    ]);
}

it('connects every Meta ad account, picking the Page when there is only one', function () {
    Http::preventStrayRequests();
    $user = User::factory()->create();
    $organization = Organization::factory()->create();
    $organization->users()->attach($user, ['role' => 'owner']);
    $project = Project::factory()->for($organization)->create();
    $app = PlatformApp::factory()->create(['platform' => PlatformAppKind::MetaAds]);

    Http::fake([
        META_GRAPH.'/oauth/access_token*' => Http::sequence()
            ->push(['access_token' => 'short', 'expires_in' => 3600])
            ->push(['access_token' => 'long', 'expires_in' => 5_184_000]),
        META_GRAPH.'/me/accounts*' => Http::response(['data' => [['id' => '42', 'name' => 'Acme']]]),
        META_GRAPH.'/me/adaccounts*' => Http::response(['data' => [
            ['account_id' => '149', 'name' => 'Acme ads', 'currency' => 'EUR', 'timezone_name' => 'Europe/Brussels'],
        ]]),
    ]);

    $this->actingAs($user)
        ->withSession(['ads_oauth' => ['state' => 'the-state', 'project_id' => $project->id, 'platform_app_id' => $app->id]])
        ->get(route('oauth.ads.callback', ['platform' => 'meta_ads', 'code' => 'a-code', 'state' => 'the-state']))
        ->assertRedirect(route('ads.index', ['project' => $project]));

    expect(AdAccount::query()->where('project_id', $project->id)->sole())
        ->platform->toBe(AdPlatform::MetaAds)
        ->external_id->toBe('149')
        ->identities->toEqual([['id' => '42', 'name' => 'Acme']])
        // The only Page on offer is the obvious one.
        ->identity_id->toBe('42')
        ->enabled->toBeTrue()
        ->time_zone->toBe('Europe/Brussels')
        // Meta has no refresh token: the long-lived token is traded for a new one.
        ->access_token->toBe('long')
        ->refresh_token->toBe('long');
    Http::assertSent(fn (Request $request): bool => str_contains($request->url(), 'fb_exchange_token=short'));
});

it('leaves a Meta account off when several Pages are on offer, and a reconnect keeps the Page picked', function () {
    Http::preventStrayRequests();
    $account = metaAccount(['identity_id' => null, 'enabled' => false]);
    $pages = ['data' => [['id' => '42', 'name' => 'Acme'], ['id' => '43', 'name' => 'Acme Labs']]];
    $adAccounts = ['data' => [['account_id' => '149', 'name' => 'Acme ads', 'currency' => 'EUR']]];

    Http::fake([
        META_GRAPH.'/me/accounts*' => Http::response($pages),
        META_GRAPH.'/me/adaccounts*' => Http::response($adAccounts),
    ]);
    $connect = fn () => app(ConnectAdAccounts::class)->handle($account->project, AdPlatform::MetaAds, $account->platformApp, new AdTokens('at', 'at', null));

    $connect();
    expect($account->fresh())->identity_id->toBeNull()->enabled->toBeFalse();

    $account->update(['identity_id' => '43', 'enabled' => true]);
    $connect();
    expect($account->fresh())->identity_id->toBe('43')->enabled->toBeTrue();
});

it('lets a person pick the Page an account posts as, and refuses to switch it on before', function () {
    $user = User::factory()->create();
    $organization = Organization::factory()->create();
    $organization->users()->attach($user, ['role' => 'owner']);
    $project = Project::factory()->for($organization)->create();
    $account = metaAccount(['project_id' => $project->id, 'identity_id' => null, 'enabled' => false]);
    $url = route('ads.accounts.update', ['project' => $project, 'adAccount' => $account->id]);

    $this->actingAs($user)->put($url, ['enabled' => true])
        ->assertSessionHasErrors(['account' => 'Pick the Facebook Page these ads are posted as first.']);
    $this->actingAs($user)->put($url, ['identity_id' => '99'])->assertSessionHasErrors('identity_id');

    $this->actingAs($user)->put($url, ['identity_id' => '43'])->assertSessionHas('status', 'Ads on this account will be posted as Acme Labs.');
    $this->actingAs($user)->put($url, ['enabled' => true])->assertSessionHasNoErrors();

    expect($account->fresh())->identity_id->toBe('43')->enabled->toBeTrue();
});

it('launches a Meta campaign paused, its budget in cents, its countries on one ad set, one ad per draft', function () {
    Http::preventStrayRequests();
    $account = metaAccount();
    $campaign = AdCampaign::factory()->create([
        'ad_account_id' => $account->id,
        'daily_budget_micros' => 12_500_000,
        'targeting' => ['countries' => ['fr', 'be']],
    ]);
    $ads = Ad::factory()->count(2)->create(['ad_campaign_id' => $campaign->id]);

    Http::fake([
        META_GRAPH.'/act_149/campaigns' => Http::response(['id' => '555']),
        META_GRAPH.'/act_149/adsets' => Http::response(['id' => '666']),
        META_GRAPH.'/act_149/adcreatives' => Http::sequence()->push(['id' => 'c1'])->push(['id' => 'c2']),
        META_GRAPH.'/act_149/ads' => Http::sequence()->push(['id' => '111'])->push(['id' => '112']),
    ]);

    $launched = app(MetaAdsClient::class)->createCampaign($campaign);

    expect($launched->externalId)->toBe('555')
        ->and($launched->ref)->toBe(['ad_set' => '666'])
        ->and($launched->adExternalIds)->toBe([$ads[0]->id => '111', $ads[1]->id => '112']);

    Http::assertSent(fn (Request $request): bool => str_ends_with($request->url(), '/campaigns')
        && $request['status'] === 'PAUSED'
        && $request['daily_budget'] === 1250);
    Http::assertSent(fn (Request $request): bool => str_ends_with($request->url(), '/adsets')
        && $request['campaign_id'] === '555'
        && $request['targeting']['geo_locations']['countries'] === ['FR', 'BE']
        && $request['dsa_beneficiary'] === $campaign->project->name);
    Http::assertSent(fn (Request $request): bool => str_ends_with($request->url(), '/adcreatives')
        && $request['object_story_spec']['page_id'] === '42'
        && $request['object_story_spec']['link_data']['name'] === $ads[0]->creative->headlines[0]);
});

it('deletes a half-built Meta campaign when an ad is refused', function () {
    Http::preventStrayRequests();
    $account = metaAccount();
    $campaign = AdCampaign::factory()->create(['ad_account_id' => $account->id, 'targeting' => ['countries' => ['FR']]]);
    Ad::factory()->create(['ad_campaign_id' => $campaign->id]);

    Http::fake([
        META_GRAPH.'/act_149/campaigns' => Http::response(['id' => '555']),
        META_GRAPH.'/act_149/adsets' => Http::response(['id' => '666']),
        META_GRAPH.'/act_149/adcreatives' => Http::response(['error' => [
            'code' => 100,
            'message' => 'Invalid parameter',
            'error_user_msg' => 'The link is not reachable.',
        ]], 400),
        META_GRAPH.'/555' => Http::response(['success' => true]),
    ]);

    expect(fn () => app(MetaAdsClient::class)->createCampaign($campaign))
        ->toThrow(AdPlatformException::class, 'Meta: The link is not reachable.');

    Http::assertSent(fn (Request $request): bool => $request->method() === 'DELETE' && str_ends_with($request->url(), '/555'));
});

it('refuses to launch on Meta without a Page or a country, before calling Meta', function (?string $identityId, array $countries, string $message) {
    Http::preventStrayRequests();
    $account = metaAccount(['identity_id' => $identityId]);
    $campaign = AdCampaign::factory()->create(['ad_account_id' => $account->id, 'targeting' => ['countries' => $countries]]);

    expect(fn () => app(MetaAdsClient::class)->createCampaign($campaign))
        ->toThrow(AdPlatformException::class, $message);
})->with([
    'no page' => [null, ['FR'], 'pick one for this account'],
    'no country' => ['42', [], 'at least one country'],
]);

it('reads Meta insights as link clicks, spend in micros and sign-ups, keeping only the ads Eveil created', function () {
    Http::preventStrayRequests();
    $account = metaAccount();
    $ad = Ad::factory()->active()->create(['ad_campaign_id' => AdCampaign::factory()->active()->create(['ad_account_id' => $account->id])->id, 'external_id' => '111']);
    $day = now()->subDay()->toDateString();

    Http::fake([
        META_GRAPH.'/act_149/insights*' => Http::sequence()
            ->push([
                'data' => [[
                    'ad_id' => '111', 'date_start' => $day, 'impressions' => '1200', 'inline_link_clicks' => '30', 'spend' => '12.50',
                    'actions' => [
                        ['action_type' => 'complete_registration', 'value' => '2'],
                        ['action_type' => 'post_reaction', 'value' => '40'],
                    ],
                ]],
                'paging' => ['next' => 'https://graph.facebook.com/v23.0/act_149/insights?after=abc'],
            ])
            // Not ours: an ad the user runs by hand.
            ->push(['data' => [['ad_id' => '999', 'date_start' => $day, 'impressions' => '500', 'inline_link_clicks' => '80', 'spend' => '20']]]),
        META_GRAPH.'/act_149/adspixels*' => Http::response(['data' => [['id' => 'px']]]),
    ]);

    app(SyncAdMetrics::class)->handle($account);

    expect(AdMetric::query()->sole())
        ->ad_id->toBe($ad->id)
        ->impressions->toBe(1200)
        ->clicks->toBe(30)
        ->cost_micros->toBe(12_500_000)
        ->conversions->toBe(2.0);
    expect($account->fresh())
        ->has_conversion_tracking->toBeTrue()
        ->last_error->toBeNull();
});
