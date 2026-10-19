<?php

use App\Actions\ApplyAdDecision as ApplyAdDecisionAction;
use App\Actions\DecideAdDecision;
use App\Actions\EnforceAdBudget;
use App\Enums\AdAccountStatus;
use App\Enums\AdDecisionStatus;
use App\Enums\AdDecisionType;
use App\Enums\AdStatus;
use App\Enums\AutonomyLevel;
use App\Jobs\ApplyAdDecision;
use App\Models\Ad;
use App\Models\AdAccount;
use App\Models\AdCampaign;
use App\Models\AdCreative;
use App\Models\AdDecision;
use App\Models\AdMetric;
use App\Models\Organization;
use App\Models\Project;
use App\Models\User;
use App\Services\Ads\AdProposals;
use App\Support\CurrentProject;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;

/**
 * @return array{0: Project, 1: AdAccount, 2: User}
 */
function adSetup(AutonomyLevel $autonomy = AutonomyLevel::Supervised): array
{
    $user = User::factory()->create();
    $organization = Organization::factory()->create();
    $organization->users()->attach($user, ['role' => 'owner']);
    $project = Project::factory()->for($organization)->create(['url' => 'https://acme.test', 'ads_autonomy_level' => $autonomy]);
    $account = AdAccount::factory()->create(['project_id' => $project->id, 'external_id' => '1234567890', 'monthly_budget_micros' => 300_000_000]);

    app(CurrentProject::class)->set($project);

    return [$project, $account, $user];
}

/**
 * @return list<array<string, mixed>>
 */
function twoAds(): array
{
    return [
        ['angle' => 'Speed', 'headlines' => ['Find clients fast', 'Cold email on autopilot', 'Start free'], 'descriptions' => ['Paste your URL, get clients.', 'Open source.'], 'final_url' => 'https://acme.test/pricing'],
        ['angle' => 'Price', 'headlines' => ['No per seat pricing', 'Unlimited mailboxes', 'Start free'], 'descriptions' => ['Self host it for free.', 'Open source.']],
    ];
}

function launch(Project $project, AdAccount $account, float $dailyBudget = 10, ?array $ads = null): string
{
    return app(AdProposals::class)->launchCampaign(
        $project, null, $account->id, 'Cold email FR', 'French founders search for a cold email tool.', null, $dailyBudget,
        ['keywords' => ['outil cold email'], 'countries' => ['FR'], 'languages' => ['fr']],
        $ads ?? twoAds(),
        'No campaign covers this intent yet.',
    );
}

it('asks for approval before spending more, at each autonomy level', function (AdDecisionType $type, AutonomyLevel $level, bool $waits) {
    expect($type->needsApproval($level, cutsSpend: false))->toBe($waits);
})->with([
    [AdDecisionType::LaunchCampaign, AutonomyLevel::Supervised, true],
    [AdDecisionType::LaunchCampaign, AutonomyLevel::SemiAuto, true],
    [AdDecisionType::LaunchCampaign, AutonomyLevel::Autonomous, false],
    [AdDecisionType::AddAd, AutonomyLevel::Supervised, true],
    [AdDecisionType::AddAd, AutonomyLevel::SemiAuto, false],
    [AdDecisionType::SetDailyBudget, AutonomyLevel::SemiAuto, false],
]);

it('never waits for approval to cut spend, even supervised', function () {
    Queue::fake([ApplyAdDecision::class]);
    [$project, $account] = adSetup();
    $campaign = AdCampaign::factory()->active()->create(['ad_account_id' => $account->id]);

    $answer = app(AdProposals::class)->pauseCampaign($project, null, $campaign->id, 'Spent 40 EUR, no conversion.');

    expect($answer)->toContain('is being applied');
    Queue::assertPushed(ApplyAdDecision::class, fn (ApplyAdDecision $job): bool => $job->decision->type === AdDecisionType::PauseCampaign);
});

it('stores a supervised launch as drafts waiting for the user', function () {
    Queue::fake([ApplyAdDecision::class]);
    [$project, $account] = adSetup();

    $answer = launch($project, $account);

    expect($answer)->toContain("waits for the user's approval");
    Queue::assertNotPushed(ApplyAdDecision::class);

    $campaign = AdCampaign::query()->sole();
    expect($campaign->status)->toBe(AdStatus::Draft)
        ->and($campaign->daily_budget_micros)->toBe(10_000_000)
        ->and($campaign->ads()->where('status', AdStatus::Draft)->count())->toBe(2)
        // The second ad gave no URL: the product's home page.
        ->and(AdCreative::query()->pluck('final_url')->all())->toEqualCanonicalizing(['https://acme.test/pricing', 'https://acme.test']);
});

it('applies an autonomous launch straight away', function () {
    Queue::fake([ApplyAdDecision::class]);
    [$project, $account] = adSetup(AutonomyLevel::Autonomous);

    launch($project, $account);

    Queue::assertPushed(ApplyAdDecision::class);
});

it('refuses copy over the network limits and stores nothing', function () {
    [$project, $account] = adSetup();

    $answer = launch($project, $account, ads: [
        ['headlines' => ['A headline far too long for any search ad', 'Two'], 'descriptions' => ['One.', 'Two.']],
    ]);

    expect($answer)
        ->toContain('Give 3 to 15 headlines, not 2.')
        ->toContain('Headline 1 is 41 characters, the limit is 30.')
        ->and(AdCampaign::query()->count())->toBe(0)
        ->and(AdCreative::query()->count())->toBe(0);
});

it('refuses to send people anywhere but the product site', function () {
    [$project, $account] = adSetup();

    $ads = twoAds();
    $ads[0]['final_url'] = 'https://competitor.test';

    expect(launch($project, $account, ads: $ads))->toContain("final_url must be on the product's own site (acme.test)");
});

it('treats an account from another project as unknown', function () {
    [$project] = adSetup();
    $foreign = AdAccount::factory()->create();

    expect(launch($project, $foreign))->toBe("No ad account {$foreign->id} on this project.");
});

it('blocks a launch the rest of the month cannot afford, and discards its drafts', function () {
    $this->travelTo(now('Europe/Paris')->setDate(2026, 10, 21)->setTime(12, 0));
    [$project, $account] = adSetup();

    // 300 left, 11 days to go: 30 a day would need 330.
    $answer = launch($project, $account, dailyBudget: 30);

    expect($answer)->toContain('Over the monthly budget')->toContain('At most 27.27 EUR more a day fits.');
    expect(AdDecision::query()->sole()->status)->toBe(AdDecisionStatus::Blocked)
        ->and(AdCampaign::query()->sole()->status)->toBe(AdStatus::Removed);
});

it('blocks spending on an account without a monthly budget', function () {
    [$project, $account] = adSetup();
    $account->update(['monthly_budget_micros' => 0]);

    expect(launch($project, $account))->toContain('no monthly budget set');
});

it('caps new campaigns per account per week', function () {
    [$project, $account] = adSetup();
    AdCampaign::factory()->count(2)->create(['ad_account_id' => $account->id, 'status' => AdStatus::Active]);

    expect(launch($project, $account))->toContain('At most 2 new campaigns per week');
});

it('caps how far one step raises a daily budget', function () {
    [$project, $account] = adSetup(AutonomyLevel::Autonomous);
    $campaign = AdCampaign::factory()->active()->create(['ad_account_id' => $account->id, 'daily_budget_micros' => 10_000_000]);

    $answer = app(AdProposals::class)->setDailyBudget($project, null, $campaign->id, 14, 'Winner.');

    expect($answer)->toContain('may rise by at most 30% in one step, from 10.00 EUR to 13.00 EUR');
});

it('lets the user reject a launch with a reason the agent reads back', function () {
    [$project, $account, $user] = adSetup();
    launch($project, $account);
    $decision = AdDecision::query()->sole();

    app(DecideAdDecision::class)->reject($decision, $user, 'We do not sell in France yet.');

    expect($decision->fresh())
        ->status->toBe(AdDecisionStatus::Rejected)
        ->outcome->toBe('Rejected by the user: We do not sell in France yet.');
    expect(AdCampaign::query()->sole()->status)->toBe(AdStatus::Removed)
        ->and(Ad::query()->where('status', '!=', AdStatus::Removed)->count())->toBe(0);
});

it('launches an approved campaign on Google Ads paused, then enables it', function () {
    Http::preventStrayRequests();
    [$project, $account, $user] = adSetup();
    launch($project, $account);
    $decision = AdDecision::query()->sole();

    Http::fake([
        'googleads.googleapis.com/v25/customers/1234567890/googleAds:search' => Http::response(['results' => [[
            'geoTargetConstant' => ['resourceName' => 'geoTargetConstants/2250'],
            'languageConstant' => ['resourceName' => 'languageConstants/1002'],
        ]]]),
        'googleads.googleapis.com/v25/customers/1234567890/googleAds:mutate' => Http::response(['mutateOperationResponses' => [
            ['campaignBudgetResult' => ['resourceName' => 'customers/1234567890/campaignBudgets/444']],
            ['campaignResult' => ['resourceName' => 'customers/1234567890/campaigns/555']],
            ['campaignCriterionResult' => ['resourceName' => 'customers/1234567890/campaignCriteria/555~2250']],
            ['campaignCriterionResult' => ['resourceName' => 'customers/1234567890/campaignCriteria/555~1002']],
            ['adGroupResult' => ['resourceName' => 'customers/1234567890/adGroups/666']],
            ['adGroupCriterionResult' => ['resourceName' => 'customers/1234567890/adGroupCriteria/666~1']],
            ['adGroupAdResult' => ['resourceName' => 'customers/1234567890/adGroupAds/666~111']],
            ['adGroupAdResult' => ['resourceName' => 'customers/1234567890/adGroupAds/666~112']],
        ]]),
        'googleads.googleapis.com/v25/customers/1234567890/campaigns:mutate' => Http::response(['results' => [['resourceName' => 'customers/1234567890/campaigns/555']]]),
    ]);

    app(DecideAdDecision::class)->approve($decision, $user);

    $campaign = AdCampaign::query()->sole();
    expect($decision->fresh()->status)->toBe(AdDecisionStatus::Applied)
        ->and($campaign->status)->toBe(AdStatus::Active)
        ->and($campaign->external_id)->toBe('555')
        ->and($campaign->external_ref)->toBe(['budget' => '444', 'ad_group' => '666'])
        ->and($campaign->ads()->orderBy('id')->pluck('external_id')->all())->toBe(['111', '112']);

    Http::assertSent(fn (Request $request): bool => str_ends_with($request->url(), 'googleAds:mutate')
        && $request['mutateOperations'][1]['campaignOperation']['create']['status'] === 'PAUSED'
        && $request['mutateOperations'][0]['campaignBudgetOperation']['create']['amountMicros'] === '10000000');
    Http::assertSent(fn (Request $request): bool => str_ends_with($request->url(), 'campaigns:mutate')
        && $request['operations'][0]['update']['status'] === 'ENABLED');
});

it('re-checks the guardrails when applying an approval', function () {
    [$project, $account, $user] = adSetup();
    launch($project, $account);
    $decision = AdDecision::query()->sole();

    // Spent in the meantime: the whole month is gone.
    AdMetric::factory()->create(['ad_id' => Ad::factory()->active()->create(['ad_campaign_id' => AdCampaign::factory()->active()->create(['ad_account_id' => $account->id])->id])->id, 'cost_micros' => 300_000_000]);

    app(DecideAdDecision::class)->approve($decision, $user);

    expect($decision->fresh()->status)->toBe(AdDecisionStatus::Blocked);
});

it('records a network refusal, and flags the account when access is gone', function () {
    Http::preventStrayRequests();
    [$project, $account] = adSetup();
    $campaign = AdCampaign::factory()->active()->create(['ad_account_id' => $account->id]);
    $decision = AdDecision::factory()->create(['ad_campaign_id' => $campaign->id, 'type' => AdDecisionType::PauseCampaign]);

    Http::fake(['googleads.googleapis.com/*' => Http::response(['error' => ['message' => 'The caller does not have permission']], 403)]);

    app(ApplyAdDecisionAction::class)->handle($decision);

    expect($decision->fresh())
        ->status->toBe(AdDecisionStatus::Failed)
        ->outcome->toBe('Google Ads refused access: The caller does not have permission');
    expect($account->fresh()->status)->toBe(AdAccountStatus::Error);
});

it('pauses everything once the month is spent', function () {
    Queue::fake([ApplyAdDecision::class]);
    [, $account] = adSetup();
    $ad = Ad::factory()->active()->create(['ad_campaign_id' => AdCampaign::factory()->active()->create(['ad_account_id' => $account->id])->id]);
    AdMetric::factory()->create(['ad_id' => $ad->id, 'cost_micros' => 300_000_000]);

    app(EnforceAdBudget::class)->handle($account);

    Queue::assertPushed(ApplyAdDecision::class, fn (ApplyAdDecision $job): bool => $job->decision->reasoning === 'The monthly budget is spent.');
});

it('leaves campaigns running while the month still has money', function () {
    Queue::fake([ApplyAdDecision::class]);
    [, $account] = adSetup();
    AdCampaign::factory()->active()->create(['ad_account_id' => $account->id]);

    app(EnforceAdBudget::class)->handle($account);

    Queue::assertNotPushed(ApplyAdDecision::class);
});
