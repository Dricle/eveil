<?php

use App\Actions\ReviewAds;
use App\Actions\SyncAdMetrics;
use App\Ai\Agents\AdStrategist;
use App\Enums\AdVerdict;
use App\Models\Ad;
use App\Models\AdAccount;
use App\Models\AdCampaign;
use App\Models\AdMetric;
use App\Models\Project;
use App\Services\Ads\AdClassifier;
use App\Services\Ads\AdPerformanceReport;
use App\Support\CurrentProject;
use Illuminate\Support\Facades\Http;
use Laravel\Ai\Prompts\AgentPrompt;

function reportedAd(AdAccount $account, array $metrics): Ad
{
    $ad = Ad::factory()->active()->create(['ad_campaign_id' => AdCampaign::factory()->active()->create(['ad_account_id' => $account->id])->id]);
    AdMetric::factory()->create(['ad_id' => $ad->id, ...$metrics]);

    return $ad;
}

it('reads the last week again and keeps only the ads Eveil created', function () {
    Http::preventStrayRequests();
    $account = AdAccount::factory()->create(['external_id' => '1234567890']);
    $ad = Ad::factory()->active()->create(['ad_campaign_id' => AdCampaign::factory()->active()->create(['ad_account_id' => $account->id])->id, 'external_id' => '111']);
    AdMetric::factory()->create(['ad_id' => $ad->id, 'date' => now()->subDay()->toDateString(), 'clicks' => 3]);

    Http::fake([
        'googleads.googleapis.com/v25/customers/1234567890/googleAds:search' => Http::sequence()
            ->push(['results' => [
                ['adGroupAd' => ['ad' => ['id' => '111']], 'segments' => ['date' => now()->subDay()->toDateString()], 'metrics' => ['impressions' => '900', 'clicks' => '30', 'costMicros' => '12500000', 'conversions' => 1.5]],
                // Not ours: a campaign the user runs by hand.
                ['adGroupAd' => ['ad' => ['id' => '999']], 'segments' => ['date' => now()->subDay()->toDateString()], 'metrics' => ['clicks' => '80']],
            ]])
            ->push(['results' => [['conversionAction' => ['id' => '1']]]]),
    ]);

    app(SyncAdMetrics::class)->handle($account);

    // Revised in place, not appended.
    expect(AdMetric::query()->sole())
        ->clicks->toBe(30)
        ->cost_micros->toBe(12_500_000)
        ->conversions->toBe(1.5)
        ->impressions->toBe(900);
    expect($account->fresh())
        ->has_conversion_tracking->toBeTrue()
        ->metrics_synced_at->not->toBeNull();
});

it('gives no verdict before an ad has spent or been clicked enough', function () {
    expect(app(AdClassifier::class)->verdict(trials: 500, successes: 20, clicks: 20, costMicros: 5_000_000, baselineRate: 0.02))->toBe(AdVerdict::Learning);
});

it('calls a winner or a loser only when the interval clears the baseline', function (int $successes, AdVerdict $verdict) {
    expect(app(AdClassifier::class)->verdict(trials: 2000, successes: $successes, clicks: $successes, costMicros: 50_000_000, baselineRate: 0.03))->toBe($verdict);
})->with([
    'clearly above' => [120, AdVerdict::Winner],
    'clearly below' => [20, AdVerdict::Loser],
    'within noise' => [62, AdVerdict::Flat],
]);

it('hands the agent ratios, intervals and pacing, never a fake zero', function () {
    $account = AdAccount::factory()->create();
    app(CurrentProject::class)->set($account->project);
    reportedAd($account, ['impressions' => 1000, 'clicks' => 40, 'cost_micros' => 20_000_000, 'conversions' => 0]);

    $report = app(AdPerformanceReport::class)->build($account->project);
    $ad = $report['accounts'][0]['campaigns'][0]['ads'][0];

    expect($ad['lifetime'])->toMatchArray([
        'impressions' => 1000,
        'clicks' => 40,
        'cost' => 20.0,
        'ctr_percent' => 4.0,
        'cpc' => 0.5,
        'cost_per_conversion' => null,
    ])
        ->and($ad['ctr_95_interval'][0])->toBeLessThan(4.0)
        ->and($report['budget']['spent_this_month'])->toBe(20.0)
        ->and($report['budget']['remaining'])->toBe(280.0)
        ->and($report['accounts'][0]['usable'])->toBeTrue()
        ->and($report['accounts'][0]['optimizing_for'])->toStartWith('clicks');
});

it('hands the whole report to the agent', function () {
    $account = AdAccount::factory()->create();
    app(CurrentProject::class)->set($account->project);
    AdStrategist::fake(['Nothing to change today.']);

    app(ReviewAds::class)->handle($account->project);

    AdStrategist::assertPrompted(fn (AgentPrompt $prompt): bool => str_contains($prompt->prompt, '"monthly": 300'));
});

it('costs no model call without an ads budget', function () {
    $project = Project::factory()->create();
    AdAccount::factory()->create(['project_id' => $project->id]);
    AdStrategist::fake();

    app(ReviewAds::class)->handle($project);

    AdStrategist::assertNeverPrompted();
});
