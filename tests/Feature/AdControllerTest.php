<?php

use App\Enums\AdDecisionStatus;
use App\Enums\AdStatus;
use App\Enums\AutonomyLevel;
use App\Jobs\ApplyAdDecision;
use App\Jobs\ReviewAds;
use App\Jobs\SyncAdAccount;
use App\Models\AdAccount;
use App\Models\AdCampaign;
use App\Models\AdDecision;
use App\Models\Organization;
use App\Models\PlatformApp;
use App\Models\Project;
use App\Models\User;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Inertia\Testing\AssertableInertia as Assert;

/**
 * @return array{0: User, 1: Project, 2: AdAccount}
 */
function adOwner(): array
{
    $user = User::factory()->create();
    $organization = Organization::factory()->create();
    $organization->users()->attach($user, ['role' => 'owner']);
    $project = Project::factory()->for($organization)->create();
    $account = AdAccount::factory()->create(['project_id' => $project->id, 'external_id' => '1234567890']);

    return [$user, $project, $account];
}

it('shows the accounts, campaigns and the decisions waiting first', function () {
    [$user, , $account] = adOwner();
    $campaign = AdCampaign::factory()->active()->create(['ad_account_id' => $account->id]);
    AdDecision::factory()->create(['ad_campaign_id' => $campaign->id, 'status' => AdDecisionStatus::Applied]);
    $waiting = AdDecision::factory()->create(['ad_campaign_id' => $campaign->id]);

    $this->actingAs($user)->get(route('ads.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->component('ads/Index')
            ->has('accounts', 1)
            ->has('campaigns', 1)
            ->where('decisions.0.id', $waiting->id)
            ->where('platforms.0.configured', true));
});

it('saves a monthly budget in micros and syncs right away', function () {
    Queue::fake([SyncAdAccount::class]);
    [$user, , $account] = adOwner();

    $this->actingAs($user)->putJson(route('ads.accounts.update', $account->id), ['monthly_budget' => 450.5])->assertRedirect();

    expect($account->fresh()->monthly_budget_micros)->toBe(450_500_000);
    Queue::assertPushed(SyncAdAccount::class);
});

it('does not reach another project\'s ad account', function () {
    [$user] = adOwner();
    $foreign = AdAccount::factory()->create(['monthly_budget_micros' => 100_000_000]);

    $this->actingAs($user)->putJson(route('ads.accounts.update', $foreign->id), ['monthly_budget' => 1])->assertNotFound();

    expect($foreign->fresh()->monthly_budget_micros)->toBe(100_000_000);
});

it('refuses to disconnect an account that still runs campaigns', function () {
    [$user, , $account] = adOwner();
    AdCampaign::factory()->active()->create(['ad_account_id' => $account->id]);

    $this->actingAs($user)->delete(route('ads.accounts.destroy', $account->id))->assertSessionHasErrors('account');

    expect($account->fresh())->not->toBeNull();
});

it('approves a waiting decision', function () {
    Queue::fake([ApplyAdDecision::class]);
    [$user, , $account] = adOwner();
    $decision = AdDecision::factory()->create(['ad_campaign_id' => AdCampaign::factory()->active()->create(['ad_account_id' => $account->id])->id]);

    $this->actingAs($user)->putJson(route('ads.decisions.update', $decision->id), ['decision' => 'approve'])->assertRedirect();

    expect($decision->fresh())->status->toBe(AdDecisionStatus::Approved)->decided_by->toBe($user->id);
    Queue::assertPushed(ApplyAdDecision::class);
});

it('does not let anyone decide another project\'s decision', function () {
    [$user] = adOwner();
    $foreign = AdDecision::factory()->create();

    $this->actingAs($user)->putJson(route('ads.decisions.update', $foreign->id), ['decision' => 'approve'])->assertNotFound();

    expect($foreign->fresh()->status)->toBe(AdDecisionStatus::Proposed);
});

it('starts a review on demand only once an account has a budget', function () {
    Queue::fake([ReviewAds::class]);
    [$user, , $account] = adOwner();
    $account->update(['monthly_budget_micros' => 0]);

    $this->actingAs($user)->post(route('ads.reviews.store'))->assertSessionHasErrors('review');
    Queue::assertNotPushed(ReviewAds::class);

    $account->update(['monthly_budget_micros' => 100_000_000]);
    $this->actingAs($user)->post(route('ads.reviews.store'))->assertSessionHasNoErrors();
    Queue::assertPushed(ReviewAds::class);
});

it('connects every ad account the grant reaches, skipping manager accounts', function () {
    Http::preventStrayRequests();
    [$user, $project] = adOwner();
    $app = PlatformApp::query()->sole();

    Http::fake([
        'oauth2.googleapis.com/token' => Http::response(['access_token' => 'at', 'refresh_token' => 'rt', 'expires_in' => 3599]),
        'googleads.googleapis.com/v25/customers:listAccessibleCustomers' => Http::response(['resourceNames' => ['customers/111', 'customers/222']]),
        'googleads.googleapis.com/v25/customers/111/googleAds:search' => Http::response(['results' => [['customer' => ['id' => '111', 'descriptiveName' => 'Acme', 'currencyCode' => 'EUR', 'timeZone' => 'Europe/Paris', 'manager' => false]]]]),
        'googleads.googleapis.com/v25/customers/222/googleAds:search' => Http::response(['results' => [['customer' => ['id' => '222', 'descriptiveName' => 'Agency', 'currencyCode' => 'EUR', 'manager' => true]]]]),
    ]);

    $this->actingAs($user)
        ->withSession(['ads_oauth' => ['state' => 'the-state', 'project_id' => $project->id, 'platform_app_id' => $app->id]])
        ->get(route('oauth.ads.callback', ['platform' => 'google_ads', 'code' => 'a-code', 'state' => 'the-state']))
        ->assertRedirect(route('ads.index', ['project' => $project]));

    $connected = AdAccount::query()->where('project_id', $project->id)->where('external_id', '111')->sole();
    expect($connected)
        ->name->toBe('Acme')
        ->access_token->toBe('at')
        ->monthly_budget_micros->toBe(0);
    expect(AdAccount::query()->where('external_id', '222')->exists())->toBeFalse();
});

it('refuses a callback whose state does not match', function () {
    [$user, $project] = adOwner();

    $this->actingAs($user)
        ->withSession(['ads_oauth' => ['state' => 'the-state', 'project_id' => $project->id, 'platform_app_id' => PlatformApp::query()->sole()->id]])
        ->get(route('oauth.ads.callback', ['platform' => 'google_ads', 'code' => 'a-code', 'state' => 'forged']))
        ->assertSessionHas('status', 'Google Ads connection failed: the request could not be verified.');
});

it('keeps an organization\'s own app secret when the edit leaves it blank', function () {
    [$user, $project] = adOwner();

    $this->actingAs($user)->putJson(route('settings.platform-apps.update', 'google_ads'), ['client_id' => 'own-id', 'client_secret' => 'own-secret'])->assertRedirect();
    $this->actingAs($user)->putJson(route('settings.platform-apps.update', 'google_ads'), ['client_id' => 'renamed-id', 'client_secret' => ''])->assertRedirect();

    $own = PlatformApp::query()->where('organization_id', $project->organization_id)->sole();
    expect($own)->client_id->toBe('renamed-id')->client_secret->toBe('own-secret');
    // And it now wins over the instance's app.
    expect(PlatformApp::resolve($project->organization, $own->platform)?->id)->toBe($own->id);
});

it('requires the secret the first time an organization adds its own app', function () {
    [$user] = adOwner();

    $this->actingAs($user)->putJson(route('settings.platform-apps.update', 'google_ads'), ['client_id' => 'own-id'])
        ->assertJsonValidationErrors(['client_secret' => 'The client secret field is required.']);
});

it('saves the ads autonomy level', function () {
    [$user, $project] = adOwner();

    $this->actingAs($user)->put(route('settings.autonomy.update'), [
        'email_autonomy_level' => 'supervised',
        'linkedin_autonomy_level' => 'supervised',
        'bluesky_autonomy_level' => 'supervised',
        'instagram_autonomy_level' => 'supervised',
        'ads_autonomy_level' => 'semi_auto',
    ])->assertRedirect();

    expect($project->fresh()->ads_autonomy_level)->toBe(AutonomyLevel::SemiAuto);
});

it('leaves draft campaigns out of the spend while they wait', function () {
    [, , $account] = adOwner();
    AdCampaign::factory()->create(['ad_account_id' => $account->id, 'status' => AdStatus::Draft, 'daily_budget_micros' => 50_000_000]);
    AdCampaign::factory()->active()->create(['ad_account_id' => $account->id, 'daily_budget_micros' => 10_000_000]);

    expect($account->activeDailyBudgetMicros())->toBe(10_000_000);
});
