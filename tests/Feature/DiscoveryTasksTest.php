<?php

use App\Actions\RunDiscovery;
use App\Ai\Agents\CompanyQualifier;
use App\Ai\Agents\DiscoveryPlanner;
use App\Enums\DiscoveryRunStatus;
use App\Enums\DiscoveryTaskKind;
use App\Enums\DiscoveryTaskStatus;
use App\Jobs\Discovery\QualifyCandidate;
use App\Jobs\Discovery\RunProbe;
use App\Models\Company;
use App\Models\DiscoveryRun;
use App\Models\DiscoveryTask;
use App\Models\TargetProfile;
use App\Support\CurrentProject;
use App\Support\Settings;
use Illuminate\Support\Facades\Http;
use Laravel\Ai\Prompts\AgentPrompt;

beforeEach(fn () => app(Settings::class)->set('crawl.delay_ms', 0));

function discoveryProfile(): TargetProfile
{
    return TargetProfile::factory()->create(['name' => 'Friteries wallonnes', 'is_active' => true]);
}

/**
 * The queue is synchronous under test, so dispatching the first node runs the
 * whole graph inside this call.
 */
function discover(TargetProfile $targetProfile, array $overrides = []): DiscoveryRun
{
    return app(CurrentProject::class)->run(
        $targetProfile->project,
        fn (): DiscoveryRun => app(RunDiscovery::class)->handle($targetProfile, $overrides),
    );
}

function mapReturning(string ...$websites): void
{
    Http::fake([
        '*/api/interpreter' => Http::response(['elements' => array_map(fn (string $website): array => [
            'type' => 'node',
            'id' => crc32($website),
            'tags' => ['name' => 'Friterie', 'website' => $website, 'amenity' => 'fast_food'],
        ], $websites)]),
        '*/robots.txt' => Http::response('', 404),
        '*' => Http::response('<!doctype html><html lang="fr"><body><p>Notre friterie.</p></body></html>'),
    ]);
}

function overpassPlan(): array
{
    return [
        'plan' => 'Enumerate friteries in Charleroi on the map.',
        'overpass_probes' => [[
            'area' => 'Charleroi',
            'country' => 'BE',
            'tags' => [['key' => 'amenity', 'value' => 'fast_food']],
            'why' => 'Friteries.',
        ]],
        'web_queries' => [],
    ];
}

function qualifierVerdict(): array
{
    return [
        'is_a_prospect' => true,
        'fit_score' => 88,
        'fit_reason' => 'Friterie indépendante.',
        'company_name' => 'Friterie du Centre',
        'industry' => 'Friterie',
        'size' => '1 établissement',
        'location' => 'Charleroi',
        'language' => 'fr',
    ];
}

it('records one row per node, linked to the node that queued it', function () {
    $targetProfile = discoveryProfile();

    DiscoveryPlanner::fake([overpassPlan()]);
    CompanyQualifier::fake([qualifierVerdict()]);
    mapReturning('https://friterie-centre.be');

    $run = discover($targetProfile, ['max_qualified' => 1]);

    $plan = DiscoveryTask::query()->where('kind', DiscoveryTaskKind::Plan)->sole();
    $probe = DiscoveryTask::query()->where('kind', DiscoveryTaskKind::Probe)->sole();
    $qualify = DiscoveryTask::query()->where('kind', DiscoveryTaskKind::Qualify)->sole();

    expect($plan->status)->toBe(DiscoveryTaskStatus::Succeeded)
        ->and($probe->parent_id)->toBe($plan->id)
        ->and($qualify->parent_id)->toBe($probe->id)
        // The row carries what a replay needs, and the model call it paid for.
        ->and($qualify->payload['domain'])->toBe('friterie-centre.be')
        ->and($qualify->agent_run_id)->not->toBeNull()
        ->and($plan->agent_run_id)->not->toBeNull()
        ->and($probe->agent_run_id)->toBeNull()
        ->and($run->refresh()->status)->toBe(DiscoveryRunStatus::Succeeded);
});

it('replays one node without rerunning the run', function () {
    $targetProfile = discoveryProfile();

    DiscoveryPlanner::fake([overpassPlan()]);
    // The qualifier is down the first time and answers on the replay.
    CompanyQualifier::fake(function () {
        static $calls = 0;

        return ++$calls === 1
            ? throw new RuntimeException('provider down')
            : qualifierVerdict();
    });
    mapReturning('https://friterie-centre.be');

    $run = discover($targetProfile);
    $task = DiscoveryTask::query()->where('kind', DiscoveryTaskKind::Qualify)->sole();

    expect($task->status)->toBe(DiscoveryTaskStatus::Failed)
        ->and($task->error)->toContain('friterie-centre.be')
        ->and(Company::count())->toBe(0)
        // One node failing must never take the run down with it.
        ->and($run->refresh()->status->isTerminal())->toBeTrue();

    $run->update(['status' => DiscoveryRunStatus::Running, 'finished_at' => null]);

    QualifyCandidate::dispatch($task);

    expect($task->refresh()->status)->toBe(DiscoveryTaskStatus::Succeeded)
        ->and($task->attempts)->toBe(2)
        ->and(Company::sole()->domain)->toBe('friterie-centre.be');
});

it('deletes queued nodes instead of running them once the run is stopped', function () {
    $targetProfile = discoveryProfile();

    // One flag carries both the credit ceiling and the cancel button: whatever
    // is already queued reads it on pickup and deletes itself.
    $run = DiscoveryRun::factory()->create([
        'project_id' => $targetProfile->project_id,
        'target_profile_id' => $targetProfile->id,
        'status' => DiscoveryRunStatus::Aborted,
    ]);

    $task = DiscoveryTask::factory()->create([
        'project_id' => $targetProfile->project_id,
        'discovery_run_id' => $run->id,
        'kind' => DiscoveryTaskKind::Probe,
    ]);

    Http::fake();

    RunProbe::dispatch($task);

    expect($task->refresh()->status)->toBe(DiscoveryTaskStatus::Skipped)
        ->and($task->result['failures'][0])->toContain('already stopped')
        ->and($run->refresh()->queries_used)->toBe(0);

    Http::assertNothingSent();
});

it('stops queueing candidates once the candidate budget is spent', function () {
    $targetProfile = discoveryProfile();

    DiscoveryPlanner::fake([overpassPlan()]);
    CompanyQualifier::fake([qualifierVerdict(), qualifierVerdict()]);
    mapReturning('https://une.be', 'https://deux.be', 'https://trois.be');

    $run = discover($targetProfile, ['max_companies' => 2]);

    // Never past the ceiling: a screen reporting "3 of 2" reads as a broken app
    // rather than as a cap doing its job.
    expect(DiscoveryTask::query()->where('kind', DiscoveryTaskKind::Qualify)->count())->toBe(2)
        ->and($run->refresh()->candidates_found)->toBe(2);
});

it('tells the planner how many probes the run may make', function () {
    $targetProfile = discoveryProfile();

    DiscoveryPlanner::fake([overpassPlan()]);
    CompanyQualifier::fake([qualifierVerdict()]);
    mapReturning('https://friterie-centre.be');

    discover($targetProfile, ['max_queries' => 7]);

    // Otherwise it plans twenty-two probes for a run that allows twelve, and the
    // tail is skipped — which is waste, not caution.
    DiscoveryPlanner::assertPrompted(fn (AgentPrompt $prompt): bool => str_contains((string) $prompt->prompt, 'at most 7 probes'));
});

it('says which ceiling stopped a step, in the numbers the run was given', function () {
    $targetProfile = discoveryProfile();

    // Two probes planned, one search allowed.
    DiscoveryPlanner::fake([[
        'plan' => 'Two towns.',
        'overpass_probes' => [
            ['area' => 'Charleroi', 'country' => 'BE', 'tags' => [['key' => 'amenity', 'value' => 'fast_food']], 'why' => 'Friteries.'],
            ['area' => 'Namur', 'country' => 'BE', 'tags' => [['key' => 'amenity', 'value' => 'fast_food']], 'why' => 'Friteries.'],
        ],
        'web_queries' => [],
    ]]);
    CompanyQualifier::fake([qualifierVerdict()]);
    mapReturning('https://friterie-centre.be');

    $run = discover($targetProfile, ['max_queries' => 1]);

    $skipped = DiscoveryTask::query()
        ->where('kind', DiscoveryTaskKind::Probe)
        ->where('status', DiscoveryTaskStatus::Skipped)
        ->sole();

    expect($skipped->result['failures'][0])->toContain('1 searches one run may make')
        ->and($run->refresh()->queries_used)->toBe(1);
});
