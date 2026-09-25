<?php

use App\Actions\RunDiscovery;
use App\Ai\Agents\CompanyQualifier;
use App\Ai\Agents\DiscoveryPlanner;
use App\Ai\Agents\RedditThreadTriage;
use App\Ai\OutOfCredit;
use App\Enums\AutonomyLevel;
use App\Enums\ContactSearchStatus;
use App\Enums\DiscoveryDiagnosis;
use App\Enums\DiscoveryRunStatus;
use App\Enums\DiscoveryTaskKind;
use App\Enums\DiscoveryTaskStatus;
use App\Enums\HostKind;
use App\Jobs\Discovery\QualifyCandidate;
use App\Jobs\Discovery\RunProbe;
use App\Jobs\FindCompanyContacts;
use App\Models\Company;
use App\Models\DiscoveryRun;
use App\Models\DiscoveryTask;
use App\Models\KnownHost;
use App\Models\Project;
use App\Models\TargetProfile;
use App\Support\CurrentProject;
use App\Support\Settings;
use Database\Seeders\KnownHostSeeder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Exceptions;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Laravel\Ai\Prompts\AgentPrompt;

beforeEach(function () {
    app(Settings::class)->set('crawl.delay_ms', 0);

    // Only this job: keeping a company now sends the app straight after the
    // people at it, and the queue is synchronous here, so the whole contact
    // search would otherwise run inside the qualification being tested.
    Queue::fake([FindCompanyContacts::class]);
});

function discoveryProfile(): TargetProfile
{
    return TargetProfile::factory()->create(['name' => 'Friteries wallonnes', 'is_active' => true]);
}

/**
 * The queue is synchronous under test, so dispatching the first node runs the
 * whole graph inside this call.
 */
function discover(TargetProfile $targetProfile, array $overrides = [], ?string $guidance = null): DiscoveryRun
{
    return app(CurrentProject::class)->run(
        $targetProfile->project,
        fn (): DiscoveryRun => app(RunDiscovery::class)->handle($targetProfile, $overrides, $guidance),
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

it('stops the whole run the moment the wallet is empty, without reporting it', function () {
    $targetProfile = discoveryProfile();

    Exceptions::fake();
    DiscoveryPlanner::fake([overpassPlan()]);
    CompanyQualifier::fake(fn () => throw new OutOfCredit('This project has no credits left.'));
    mapReturning('https://friterie-centre.be', 'https://friterie-gare.be');

    $run = discover($targetProfile);

    expect($run->refresh()->status)->toBe(DiscoveryRunStatus::Failed)
        ->and($run->error)->toContain('no credits left')
        ->and(DiscoveryTask::query()->where('kind', DiscoveryTaskKind::Qualify)->pluck('status')->all())
        ->toBe([DiscoveryTaskStatus::Failed, DiscoveryTaskStatus::Skipped]);

    Exceptions::assertNothingReported();
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
    // tail is skipped. Which is waste, not caution.
    DiscoveryPlanner::assertPrompted(fn (AgentPrompt $prompt): bool => str_contains((string) $prompt->prompt, 'at most 7 probes'));
});

it('hands the user\'s own guidance to the planner, for that run only', function () {
    $targetProfile = discoveryProfile();

    DiscoveryPlanner::fake([overpassPlan()]);
    CompanyQualifier::fake([qualifierVerdict()]);
    mapReturning('https://friterie-centre.be');

    $run = discover($targetProfile, guidance: 'Look at wholesalers instead of end users this time.');

    expect($run->guidance)->toBe('Look at wholesalers instead of end users this time.');

    DiscoveryPlanner::assertPrompted(fn (AgentPrompt $prompt): bool => str_contains(
        (string) $prompt->prompt,
        'Look at wholesalers instead of end users this time.',
    ));
});

it('never mentions guidance in the prompt when none was given', function () {
    $targetProfile = discoveryProfile();

    DiscoveryPlanner::fake([overpassPlan()]);
    CompanyQualifier::fake([qualifierVerdict()]);
    mapReturning('https://friterie-centre.be');

    discover($targetProfile);

    DiscoveryPlanner::assertPrompted(fn (AgentPrompt $prompt): bool => ! str_contains((string) $prompt->prompt, 'asked specifically'));
});

it('tells the planner what earlier runs for this profile already tried', function () {
    $targetProfile = discoveryProfile();

    DiscoveryRun::factory()->create([
        'project_id' => $targetProfile->project_id,
        'target_profile_id' => $targetProfile->id,
        'origin' => 'search',
        'status' => DiscoveryRunStatus::Exhausted,
        'candidates_found' => 40,
        'qualified_count' => 0,
        'finished_at' => now()->subDay(),
        'stats' => ['candidate_failures' => ['annuaire.test: blocked']],
    ]);

    DiscoveryPlanner::fake([overpassPlan()]);
    CompanyQualifier::fake([qualifierVerdict()]);
    mapReturning('https://friterie-centre.be');

    discover($targetProfile);

    DiscoveryPlanner::assertPrompted(fn (AgentPrompt $prompt): bool => str_contains((string) $prompt->prompt, 'annuaire.test: blocked')
        && str_contains((string) $prompt->prompt, '40 candidate(s), 0 qualified'));
});

it('pivots to a different source when the first wave finds nothing, budget allowing', function () {
    $targetProfile = discoveryProfile();

    DiscoveryPlanner::fake([overpassPlan(), overpassPlan()]);
    CompanyQualifier::fake([qualifierVerdict()]);

    $calls = 0;
    Http::fake([
        '*/api/interpreter' => function () use (&$calls) {
            $calls++;

            // Nothing on the first probe, a real business on the second -
            // the pivot's whole point.
            return $calls === 1
                ? Http::response(['elements' => []])
                : Http::response(['elements' => [[
                    'type' => 'node',
                    'id' => 1,
                    'tags' => ['name' => 'Friterie', 'website' => 'https://friterie-centre.be', 'amenity' => 'fast_food'],
                ]]]);
        },
        '*/robots.txt' => Http::response('', 404),
        '*' => Http::response('<!doctype html><html lang="fr"><body><p>Notre friterie.</p></body></html>'),
    ]);

    $run = discover($targetProfile, ['max_queries' => 12]);

    // Whether one lonely candidate also trips `too_narrow` is a separate,
    // already-tested concern; what this test is about is that the pivot
    // found it at all, instead of the run closing empty after wave one.
    expect($run->refresh()->diagnosis)->not->toBe(DiscoveryDiagnosis::WrongSource)
        ->and($run->candidates_found)->toBe(1)
        // The opening plan, and its one automatic do-over. Never a third.
        ->and($run->tasks()->where('kind', DiscoveryTaskKind::Plan)->count())->toBe(2);

    DiscoveryPlanner::assertPrompted(fn (AgentPrompt $prompt): bool => str_contains(
        (string) $prompt->prompt,
        'The first attempt in THIS run just found nothing',
    ));
});

it('never pivots a second time - a run empty twice is genuinely diagnosed', function () {
    $targetProfile = discoveryProfile();

    DiscoveryPlanner::fake([overpassPlan(), overpassPlan()]);

    Http::fake([
        '*/api/interpreter' => Http::response(['elements' => []]),
        '*/robots.txt' => Http::response('', 404),
    ]);

    $run = discover($targetProfile, ['max_queries' => 12]);

    expect($run->refresh()->status)->toBe(DiscoveryRunStatus::Exhausted)
        ->and($run->diagnosis)->toBe(DiscoveryDiagnosis::WrongSource)
        ->and($run->candidates_found)->toBe(0)
        ->and($run->tasks()->where('kind', DiscoveryTaskKind::Plan)->count())->toBe(2);
});

it('never pivots a run that already found something - that is too_narrow\'s job, not this', function () {
    $targetProfile = discoveryProfile();

    DiscoveryPlanner::fake([[
        'plan' => 'One probe, one miss.',
        'overpass_probes' => [
            ['area' => 'Charleroi', 'country' => 'BE', 'tags' => [['key' => 'amenity', 'value' => 'fast_food']], 'why' => 'Friteries.'],
            ['area' => 'Namur', 'country' => 'BE', 'tags' => [['key' => 'amenity', 'value' => 'fast_food']], 'why' => 'Friteries.'],
        ],
        'web_queries' => [],
    ]]);
    CompanyQualifier::fake([qualifierVerdict()]);
    mapReturning('https://friterie-centre.be');

    $run = discover($targetProfile, ['max_queries' => 12]);

    expect($run->refresh()->candidates_found)->toBe(1)
        ->and($run->tasks()->where('kind', DiscoveryTaskKind::Plan)->count())->toBe(1);
});

it('reflects mid-run: a host that produced several well-scoring companies gets a focused follow-up wave', function () {
    $targetProfile = discoveryProfile();

    // Locked and pre-classified as `index`, so Triage routes it to a harvest
    // rather than asking the model - this test is about the reflect wiring,
    // not host classification.
    KnownHost::factory()->create([
        'host' => 'producthunt.test',
        'kind' => HostKind::Index,
        'is_locked' => true,
    ]);

    DiscoveryPlanner::fake([
        [
            'plan' => 'One web query, aimed at a launch directory.',
            'overpass_probes' => [],
            'web_queries' => [['query' => 'site:producthunt.test products', 'language' => 'en', 'why' => 'Launch directory.']],
        ],
        [
            'plan' => 'producthunt.test proved productive, nothing more to add.',
            'overpass_probes' => [],
            'web_queries' => [],
        ],
    ]);

    // Three businesses harvested off the SAME listing page, each on its own
    // domain, then the listing host itself as a fourth candidate
    // (`Triage::sort()`'s "an index is also an entity") - ruled out as a
    // directory, the realistic verdict `CompanyQualifier`'s own instructions
    // already call for.
    CompanyQualifier::fake([
        ['is_a_prospect' => true, 'fit_score' => 80, 'fit_reason' => 'Fits.', 'company_name' => 'Acme One', 'industry' => 'SaaS', 'size' => 'unknown', 'location' => 'unknown', 'language' => 'en'],
        ['is_a_prospect' => true, 'fit_score' => 85, 'fit_reason' => 'Fits.', 'company_name' => 'Acme Two', 'industry' => 'SaaS', 'size' => 'unknown', 'location' => 'unknown', 'language' => 'en'],
        ['is_a_prospect' => true, 'fit_score' => 75, 'fit_reason' => 'Fits.', 'company_name' => 'Acme Three', 'industry' => 'SaaS', 'size' => 'unknown', 'location' => 'unknown', 'language' => 'en'],
        ['is_a_prospect' => false, 'fit_score' => 20, 'fit_reason' => 'A directory, not a company.', 'company_name' => 'producthunt.test', 'industry' => 'Directory', 'size' => 'unknown', 'location' => 'unknown', 'language' => 'en'],
    ]);

    Http::fake([
        '*/robots.txt' => Http::response('', 404),
        'searxng:8080/search*' => Http::response(['results' => [
            ['url' => 'https://producthunt.test/', 'title' => 'Product Hunt', 'content' => 'Launch directory.'],
        ]]),
        'https://producthunt.test/' => Http::response(
            '<!doctype html><html lang="en"><body>'
            .'<script type="application/ld+json">{"@type":"Organization","name":"Acme One","url":"https://acme1.test/"}</script>'
            .'<script type="application/ld+json">{"@type":"Organization","name":"Acme Two","url":"https://acme2.test/"}</script>'
            .'<script type="application/ld+json">{"@type":"Organization","name":"Acme Three","url":"https://acme3.test/"}</script>'
            .'</body></html>',
        ),
        '*' => Http::response('<!doctype html><html lang="en"><body><p>A real company page.</p></body></html>'),
    ]);

    $run = discover($targetProfile, ['max_queries' => 10]);

    expect($run->tasks()->where('kind', DiscoveryTaskKind::Reflect)->count())->toBe(1)
        ->and($run->productiveHosts()->keys()->all())->toBe(['producthunt.test']);

    DiscoveryPlanner::assertPrompted(fn (AgentPrompt $prompt): bool => str_contains(
        (string) $prompt->prompt,
        'producthunt.test: 3 qualified so far this run',
    ));
});

it('keeps a Reddit mention with no confirmed link as evidence, through the real Triage step', function () {
    // The real thing this guards: `reddit.com` is a LOCKED `other` host
    // (KnownHostSeeder), and a Reddit candidate's `sourceUrl` always points
    // at a reddit.com permalink. Triage used to classify by `sourceUrl` first,
    // which would have silently dropped every Reddit candidate, resolved
    // link or not, the moment this ran through the real pipeline instead of
    // RedditSource in isolation.
    $this->seed(KnownHostSeeder::class);

    $targetProfile = discoveryProfile();

    DiscoveryPlanner::fake([[
        'plan' => 'One subreddit, aimed at founders talking about their own product.',
        'overpass_probes' => [],
        'web_queries' => [],
        'reddit_probes' => [['subreddit' => 'SaaS', 'query' => 'launched', 'why' => 'Founders posting launches.']],
    ]]);

    RedditThreadTriage::fake([['items' => [[
        'permalink' => 'https://www.reddit.com/r/SaaS/comments/abc123/launched_my_saas_today/',
        'is_candidate' => true,
        'product_identifier' => 'an AI email tool',
        'reason' => 'Author says they built an AI email tool, no link given.',
    ]]]]);

    CompanyQualifier::fake([[
        'is_a_prospect' => true,
        'fit_score' => 72,
        'fit_reason' => 'Author says they built an AI email tool, no link given.',
        'company_name' => 'an AI email tool',
        'industry' => 'SaaS',
        'size' => 'unknown',
        'location' => 'unknown',
        'language' => 'en',
    ]]);

    Http::fake([
        'arctic-shift.photon-reddit.com/api/posts/search*' => function ($request) {
            parse_str((string) parse_url($request->url(), PHP_URL_QUERY), $query);

            // No link in the thread itself, and none in the author's own
            // recent posts either: the case that only survives if `evidence`
            // keeps it past `queueQualifications()`.
            return Http::response(['data' => (($query['author'] ?? null) !== null) ? [] : [[
                'title' => 'Launched my SaaS today',
                'permalink' => '/r/SaaS/comments/abc123/launched_my_saas_today/',
                'subreddit' => 'SaaS',
                'author' => 'founder123',
                'is_self' => true,
                'selftext' => 'Built an AI email tool, DM me if curious.',
            ]]]);
        },
        'arctic-shift.photon-reddit.com/api/comments/search*' => Http::response(['data' => []]),
        '*/robots.txt' => Http::response('', 404),
    ]);

    $run = discover($targetProfile, ['max_queries' => 12]);

    $company = Company::sole();

    expect($run->refresh()->candidates_found)->toBe(1)
        ->and($company->website)->toBeNull()
        ->and($company->domain)->toBeNull()
        ->and($company->facts['evidence'] ?? null)->not->toBeNull();
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

it('goes looking for the people the moment a company is kept', function () {
    $targetProfile = discoveryProfile();

    DiscoveryPlanner::fake([overpassPlan()]);
    CompanyQualifier::fake([qualifierVerdict()]);
    mapReturning('https://friterie-centre.be');

    discover($targetProfile);

    $company = Company::sole();

    // Forty companies is forty clicks nobody makes, so nobody is asked.
    expect($company->contacts_status)->toBe(ContactSearchStatus::Queued);

    Queue::assertPushed(FindCompanyContacts::class, 1);
    Queue::assertPushed(fn (FindCompanyContacts $job): bool => $job->company->is($company));
});

it('approves a company the moment it qualifies, under full autonomy', function () {
    $targetProfile = discoveryProfile();
    $targetProfile->project->update(['email_autonomy_level' => AutonomyLevel::Autonomous]);

    DiscoveryPlanner::fake([overpassPlan()]);
    CompanyQualifier::fake([qualifierVerdict()]);
    mapReturning('https://friterie-centre.be');

    discover($targetProfile);

    // No campaign involved: the approval is earned at qualification, not at
    // enrolment, so it stands even with nothing to enrol it into yet.
    expect(Company::sole()->approved_at)->not->toBeNull();
});

it('leaves a company unapproved outside full autonomy', function () {
    $targetProfile = discoveryProfile();

    DiscoveryPlanner::fake([overpassPlan()]);
    CompanyQualifier::fake([qualifierVerdict()]);
    mapReturning('https://friterie-centre.be');

    discover($targetProfile);

    expect(Company::sole()->approved_at)->toBeNull();
});

it('never queues the same company for contacts twice', function () {
    $targetProfile = discoveryProfile();

    DiscoveryPlanner::fake([overpassPlan()]);
    CompanyQualifier::fake([qualifierVerdict(), qualifierVerdict()]);
    mapReturning('https://friterie-centre.be');

    discover($targetProfile);

    $task = DiscoveryTask::query()->where('kind', DiscoveryTaskKind::Qualify)->sole();
    $run = DiscoveryRun::sole();
    $run->update(['status' => DiscoveryRunStatus::Running, 'finished_at' => null]);

    // Replaying the node re-qualifies the same company, and the column is what
    // stops a second search being queued for it.
    QualifyCandidate::dispatch($task);

    Queue::assertPushed(FindCompanyContacts::class, 1);
});

it('tells the planner to only ever probe a subreddit from the profile\'s own verified list', function () {
    $agent = new DiscoveryPlanner(
        Project::factory()->create(),
        TargetProfile::factory()->make(),
        maxProbes: 10,
        guidance: null,
        isPivot: false,
        history: new Collection,
        productiveHosts: new Collection,
    );

    expect((string) $agent->instructions())
        ->toContain('Only ever probe a name from THAT list')
        ->toContain('never asked to name a subreddit from memory');
});
