<?php

use App\Ai\Agents\CompanyQualifier;
use App\Ai\Agents\DiscoveryPlanner;
use App\Ai\Agents\ResultTriage;
use App\Enums\DiscoveryDiagnosis;
use App\Enums\DiscoveryRunStatus;
use App\Enums\HostKind;
use App\Models\Company;
use App\Models\CompanyTargetEvaluation;
use App\Models\DiscoveryRun;
use App\Models\KnownHost;
use App\Models\TargetProfile;
use App\Support\Settings;
use Database\Seeders\KnownHostSeeder;
use Illuminate\Support\Facades\Http;

function plan(array $overpass = [], array $web = []): array
{
    return [
        'plan' => 'Enumerate friteries in Charleroi on the map.',
        'overpass_probes' => $overpass,
        'web_queries' => $web,
    ];
}

function overpassProbe(string $area = 'Charleroi'): array
{
    return [
        'area' => $area,
        'country' => 'BE',
        'tags' => [['key' => 'amenity', 'value' => 'fast_food']],
        'why' => 'Friteries.',
    ];
}

function verdict(int $score = 85, bool $prospect = true): array
{
    return [
        'is_a_prospect' => $prospect,
        'fit_score' => $score,
        'fit_reason' => 'Friterie indépendante à Charleroi, commandes par téléphone uniquement.',
        'company_name' => 'Friterie du Centre',
        'industry' => 'Friterie',
        'size' => '1 établissement',
        'location' => 'Charleroi, Belgique',
        'language' => 'fr',
    ];
}

function osmElement(string $name, string $website): array
{
    return ['type' => 'node', 'id' => crc32($website), 'tags' => [
        'name' => $name, 'website' => $website, 'amenity' => 'fast_food', 'addr:city' => 'Charleroi',
    ]];
}

function page(string $body = 'Notre friterie à Charleroi, commandez par téléphone.'): string
{
    return '<!doctype html><html lang="fr"><head><title>Friterie</title></head><body><p>'.$body.'</p></body></html>';
}

function activeTargetProfile(): TargetProfile
{
    return TargetProfile::factory()->create(['name' => 'Friteries wallonnes', 'is_active' => true]);
}

beforeEach(fn () => app(Settings::class)->set('crawl.delay_ms', 0));

it('finds companies on the map, qualifies them and stores the pair', function () {
    $targetProfile = activeTargetProfile();

    DiscoveryPlanner::fake([plan(overpass: [overpassProbe()])]);
    CompanyQualifier::fake([verdict(88)]);

    Http::fake([
        '*/api/interpreter' => Http::response(['elements' => [osmElement('Friterie du Centre', 'https://friterie-centre.be')]]),
        '*/robots.txt' => Http::response('', 404),
        'https://friterie-centre.be/' => Http::response(page()),
    ]);

    $this->artisan('eveil:discover-companies')->assertSuccessful();

    $company = Company::sole();

    expect($company->domain)->toBe('friterie-centre.be')
        ->and($company->name)->toBe('Friterie du Centre')
        ->and($company->source)->toBe('overpass')
        ->and($company->language)->toBe('fr')
        ->and($company->facts['city'])->toBe('Charleroi');

    // The score belongs to the (company, profile) pair, never to the company.
    $evaluation = CompanyTargetEvaluation::sole();

    expect($evaluation->fit_score)->toBe(88)
        ->and($evaluation->target_profile_id)->toBe($targetProfile->id)
        ->and($evaluation->company_id)->toBe($company->id);
});

it('records the plan the agent explained before executing', function () {
    activeTargetProfile();
    DiscoveryPlanner::fake([plan(overpass: [overpassProbe()])]);
    CompanyQualifier::fake([verdict()]);
    Http::fake([
        '*/api/interpreter' => Http::response(['elements' => [osmElement('A', 'https://a.be')]]),
        '*/robots.txt' => Http::response('', 404),
        '*' => Http::response(page()),
    ]);

    $this->artisan('eveil:discover-companies')->assertSuccessful();

    expect(DiscoveryRun::sole()->stats['plan'])->toContain('Charleroi');
});

it('searches the web when the profile has no premises', function () {
    activeTargetProfile();
    DiscoveryPlanner::fake([plan(web: [['query' => 'dark kitchen bruxelles', 'language' => 'fr', 'why' => '...']])]);
    CompanyQualifier::fake([verdict()]);

    Http::fake([
        '*/search*' => Http::response(['results' => [
            ['url' => 'https://darkkitchen.be', 'title' => 'Dark Kitchen', 'content' => 'Cuisine 100% livraison'],
        ]]),
        '*/robots.txt' => Http::response('', 404),
        'https://darkkitchen.be/' => Http::response(page()),
    ]);

    $this->artisan('eveil:discover-companies')->assertSuccessful();

    expect(Company::sole()->source)->toBe('web_search');
});

it('sorts search results by what each host is, and harvests the lists', function () {
    // This used to assert that directories were thrown away. They are the most
    // valuable result there is: one listing page is hundreds of businesses,
    // and for a business with no site of its own it is the only place an
    // address is published. Encyclopaedias and social platforms still go.
    $this->seed(KnownHostSeeder::class);
    activeTargetProfile();
    DiscoveryPlanner::fake([plan(web: [['query' => 'friterie', 'language' => 'fr', 'why' => '...']])]);
    ResultTriage::fake([['hosts' => [
        ['host' => 'annuaire.test', 'kind' => 'index', 'reason' => 'Lists businesses.'],
        ['host' => 'vraie-friterie.be', 'kind' => 'entity', 'reason' => 'One business.'],
    ]]]);
    CompanyQualifier::fake([verdict(), verdict(), verdict()]);

    Http::fake([
        '*/search*' => Http::response(['results' => [
            ['url' => 'https://annuaire.test/friteries', 'title' => 'Top friteries'],
            ['url' => 'https://fr.wikipedia.org/wiki/Friterie', 'title' => 'Friterie'],
            ['url' => 'https://www.facebook.com/friterie', 'title' => 'Friterie'],
            ['url' => 'https://vraie-friterie.be', 'title' => 'Vraie Friterie'],
        ]]),
        '*/robots.txt' => Http::response('', 404),
        'https://annuaire.test/friteries' => Http::response(
            '<html lang="fr"><body><script type="application/ld+json">'
            .json_encode(['@type' => 'Restaurant', 'name' => 'Chez Marcel', 'url' => 'https://chez-marcel.test', 'telephone' => '+3281223344'])
            .'</script></body></html>'
        ),
        '*' => Http::response(page()),
    ]);

    $this->artisan('eveil:discover-companies')->assertSuccessful();

    // The business the directory listed, the directory itself, and the one
    // company site. Wikipedia and Facebook are gone, answered from their
    // locked rows without spending a token.
    expect(Company::pluck('domain')->sort()->values()->all())
        ->toBe(['annuaire.test', 'chez-marcel.test', 'vraie-friterie.be']);
});

it('drops map entries that have no website', function () {
    activeTargetProfile();
    DiscoveryPlanner::fake([plan(overpass: [overpassProbe()])]);
    CompanyQualifier::fake([verdict()]);

    Http::fake([
        '*/api/interpreter' => Http::response(['elements' => [
            ['type' => 'node', 'id' => 1, 'tags' => ['name' => 'Sans site', 'amenity' => 'fast_food']],
            osmElement('Avec site', 'https://avec-site.be'),
        ]]),
        '*/robots.txt' => Http::response('', 404),
        '*' => Http::response(page()),
    ]);

    $this->artisan('eveil:discover-companies')->assertSuccessful();

    // Without a domain there is nothing to qualify and no email to infer.
    expect(Company::pluck('domain')->all())->toBe(['avec-site.be']);
});

it('keeps out what the qualifier says is not a prospect', function () {
    activeTargetProfile();
    DiscoveryPlanner::fake([plan(overpass: [overpassProbe()])]);
    CompanyQualifier::fake([verdict(prospect: false)]);

    Http::fake([
        '*/api/interpreter' => Http::response(['elements' => [osmElement('Annuaire', 'https://annuaire.be')]]),
        '*/robots.txt' => Http::response('', 404),
        '*' => Http::response(page()),
    ]);

    $this->artisan('eveil:discover-companies')->assertSuccessful();

    expect(Company::count())->toBe(0)
        ->and(DiscoveryRun::sole()->diagnosis)->toBe(DiscoveryDiagnosis::BadTargetProfile);
});

it('diagnoses a wrong source rather than a wrong profile when nothing is found', function () {
    activeTargetProfile();
    DiscoveryPlanner::fake([plan(overpass: [overpassProbe()])]);

    Http::fake(['*/api/interpreter' => Http::response(['elements' => []])]);

    $this->artisan('eveil:discover-companies')
        ->expectsOutputToContain('sources were wrong')
        ->assertSuccessful();

    expect(DiscoveryRun::sole()->diagnosis)->toBe(DiscoveryDiagnosis::WrongSource)
        ->and(DiscoveryRun::sole()->status)->toBe(DiscoveryRunStatus::Exhausted);
});

it('stops at the qualified ceiling', function () {
    activeTargetProfile();
    DiscoveryPlanner::fake([plan(overpass: [overpassProbe()])]);
    CompanyQualifier::fake([verdict(), verdict(), verdict()]);

    Http::fake([
        '*/api/interpreter' => Http::response(['elements' => [
            osmElement('Un', 'https://un.be'),
            osmElement('Deux', 'https://deux.be'),
            osmElement('Trois', 'https://trois.be'),
        ]]),
        '*/robots.txt' => Http::response('', 404),
        '*' => Http::response(page()),
    ]);

    $this->artisan('eveil:discover-companies', ['--qualified' => 2])->assertSuccessful();

    // The budget is a hard ceiling: the run stops on it and keeps what it has.
    expect(Company::count())->toBe(2);
});

it('never rediscovers a company the project already has', function () {
    $targetProfile = activeTargetProfile();
    Company::factory()->create(['project_id' => $targetProfile->project_id, 'domain' => 'connu.be']);

    DiscoveryPlanner::fake([plan(overpass: [overpassProbe()])]);
    CompanyQualifier::fake([verdict()]);

    Http::fake([
        '*/api/interpreter' => Http::response(['elements' => [
            osmElement('Connu', 'https://connu.be'),
            osmElement('Nouveau', 'https://nouveau.be'),
        ]]),
        '*/robots.txt' => Http::response('', 404),
        '*' => Http::response(page()),
    ]);

    $this->artisan('eveil:discover-companies')->assertSuccessful();

    expect(Company::count())->toBe(2)
        ->and(DiscoveryRun::sole()->stats['candidates_found'])->toBe(1);
});

it('survives a source that is down', function () {
    activeTargetProfile();
    DiscoveryPlanner::fake([plan(
        overpass: [overpassProbe()],
        web: [['query' => 'friterie charleroi', 'language' => 'fr', 'why' => '...']],
    )]);
    CompanyQualifier::fake([verdict()]);

    Http::fake([
        // Overpass rate-limits constantly and SearXNG is a meta-search engine;
        // one dead source must never take the run down with it.
        '*/api/interpreter' => Http::response('', 504),
        '*/search*' => Http::response(['results' => [['url' => 'https://survivant.be', 'title' => 'Survivant']]]),
        '*/robots.txt' => Http::response('', 404),
        '*' => Http::response(page()),
    ]);

    $this->artisan('eveil:discover-companies')->assertSuccessful();

    expect(Company::sole()->domain)->toBe('survivant.be');
});

it('asks which profile when there are several', function () {
    activeTargetProfile();
    activeTargetProfile();

    $this->artisan('eveil:discover-companies')
        ->expectsOutputToContain('Several profiles')
        ->assertFailed();
});

it('scopes a map probe to its country', function () {
    activeTargetProfile();
    DiscoveryPlanner::fake([plan(overpass: [overpassProbe()])]);
    CompanyQualifier::fake([verdict()]);

    Http::fake([
        '*/api/interpreter' => Http::response(['elements' => [osmElement('A', 'https://a.be')]]),
        '*/robots.txt' => Http::response('', 404),
        '*' => Http::response(page()),
    ]);

    $this->artisan('eveil:discover-companies')->assertSuccessful();

    // A probe on "Charleroi" alone also returns Charleroi, Pennsylvania. The
    // first live run brought back a Subway there.
    Http::assertSent(function ($request) {
        return ! str_contains($request->url(), 'interpreter')
            || (str_contains(urldecode($request->body()), 'ISO3166-1"="BE')
                && str_contains(urldecode($request->body()), 'map_to_area'));
    });
});

it('says which source failed rather than blaming the profile', function () {
    activeTargetProfile();
    DiscoveryPlanner::fake([plan(overpass: [overpassProbe()])]);

    // Overpass answers 406 to an unidentified client. That happened for real,
    // and the run reported "no candidate at all" as if the market were empty.
    Http::fake(['*/api/interpreter' => Http::response('', 406)]);

    $this->artisan('eveil:discover-companies')->assertSuccessful();

    expect(DiscoveryRun::sole()->stats['source_failures'])->toContain('Charleroi: HTTP 406');
});

it('identifies itself to the map service', function () {
    activeTargetProfile();
    DiscoveryPlanner::fake([plan(overpass: [overpassProbe()])]);
    CompanyQualifier::fake([verdict()]);

    Http::fake([
        '*/api/interpreter' => Http::response(['elements' => []]),
        '*' => Http::response(page()),
    ]);

    $this->artisan('eveil:discover-companies')->assertSuccessful();

    Http::assertSent(fn ($request) => ! str_contains($request->url(), 'interpreter')
        || str_contains($request->header('User-Agent')[0] ?? '', 'EveilBot'));
});

it('keeps going when one candidate blows up', function () {
    activeTargetProfile();
    DiscoveryPlanner::fake([plan(overpass: [overpassProbe()])]);
    CompanyQualifier::fake(function ($prompt) {
        // A single mis-encoded Belgian site killed the first live run two
        // thirds of the way through and lost everything already found.
        if (str_contains((string) $prompt, 'casse.be')) {
            throw new RuntimeException('mis-encoded page');
        }

        return verdict();
    });

    Http::fake([
        '*/api/interpreter' => Http::response(['elements' => [
            osmElement('Casse', 'https://casse.be'),
            osmElement('Marche', 'https://marche.be'),
        ]]),
        '*/robots.txt' => Http::response('', 404),
        '*' => Http::response(page()),
    ]);

    $this->artisan('eveil:discover-companies')->assertSuccessful();

    expect(Company::pluck('domain')->all())->toBe(['marche.be'])
        ->and(DiscoveryRun::sole()->stats['candidate_failures'][0])->toContain('casse.be');
});

it('stores a page that Postgres would otherwise reject', function () {
    activeTargetProfile();
    DiscoveryPlanner::fake([plan(overpass: [overpassProbe()])]);
    CompanyQualifier::fake([verdict()]);

    Http::fake([
        '*/api/interpreter' => Http::response(['elements' => [osmElement('Nul', 'https://nul.be')]]),
        '*/robots.txt' => Http::response('', 404),
        // A NUL byte in the body: PostgreSQL text columns reject it outright.
        '*' => Http::response(str_replace('friterie', "friterie\0", page())),
    ]);

    $this->artisan('eveil:discover-companies')->assertSuccessful();

    expect(Company::sole()->domain)->toBe('nul.be');
});

it('harvests a directory and keeps the directory itself as a candidate', function () {
    // A directory is also a company. Someone's target profile is "launch
    // platforms" or "review sites", and treating index and entity as exclusive
    // would make that buyer unserviceable: the host would be scraped for its
    // listings and never considered as a lead.
    activeTargetProfile();

    DiscoveryPlanner::fake([plan(web: [['query' => 'friterie namur', 'language' => 'fr', 'why' => 'Local.']])]);
    ResultTriage::fake([['hosts' => [['host' => 'annuaire.test', 'kind' => 'index', 'reason' => 'Lists businesses.']]]]);
    CompanyQualifier::fake([verdict(90), verdict(20)]);

    Http::fake([
        '*/search*' => Http::response(['results' => [
            ['url' => 'https://annuaire.test/friteries/namur', 'title' => 'Friteries à Namur', 'content' => 'Annuaire'],
        ]]),
        '*/robots.txt' => Http::response('', 404),
        'https://annuaire.test/friteries/namur' => Http::response(
            '<html lang="fr"><body><script type="application/ld+json">'
            .json_encode(['@type' => 'Restaurant', 'name' => 'Chez Marcel', 'url' => 'https://chez-marcel.test', 'telephone' => '+3281223344'])
            .'</script></body></html>'
        ),
        'https://chez-marcel.test/' => Http::response(page()),
        'https://annuaire.test/' => Http::response(page('Annuaire des commerces.')),
    ]);

    $this->artisan('eveil:discover-companies')->assertSuccessful();

    // Both: the business the listing named, AND the directory running it.
    expect(Company::pluck('domain')->sort()->values()->all())
        ->toBe(['annuaire.test', 'chez-marcel.test']);
});

it('remembers a host verdict so a second run never re-asks', function () {
    activeTargetProfile();

    DiscoveryPlanner::fake([plan(web: [['query' => 'friterie namur', 'language' => 'fr', 'why' => 'Local.']])]);
    ResultTriage::fake([['hosts' => [['host' => 'marcel.test', 'kind' => 'entity', 'reason' => 'One business.']]]]);
    CompanyQualifier::fake([verdict(88), verdict(88)]);

    Http::fake([
        '*/search*' => Http::response(['results' => [
            ['url' => 'https://marcel.test/', 'title' => 'Friterie Chez Marcel', 'content' => 'Notre friterie'],
        ]]),
        '*/robots.txt' => Http::response('', 404),
        'https://marcel.test/' => Http::response(page()),
    ]);

    $this->artisan('eveil:discover-companies')->assertSuccessful();

    expect(KnownHost::query()->firstWhere('host', 'marcel.test')->kind)->toBe(HostKind::Entity);

    // Second run, different profile: the model must not be consulted again.
    ResultTriage::fake()->preventStrayPrompts();
    DiscoveryPlanner::fake([plan(web: [['query' => 'friterie liege', 'language' => 'fr', 'why' => 'Local.']])]);
    TargetProfile::factory()->create(['name' => 'Autre profil', 'is_active' => true]);

    $this->artisan('eveil:discover-companies', ['profile' => 'Autre profil'])->assertSuccessful();
});

it('sends a real directory to the registry instead of deleting it', function () {
    // Regression: `WebSearchSource` kept a hardcoded aggregator blocklist long
    // after `HostRegistry` replaced it, so a real directory was dropped before
    // the registry ever saw it and the whole feature was dead for web results.
    // The earlier tests missed it by using invented hosts.
    activeTargetProfile();

    DiscoveryPlanner::fake([plan(web: [['query' => 'friterie namur', 'language' => 'fr', 'why' => 'Local.']])]);
    ResultTriage::fake([['hosts' => [['host' => 'pagesdor.be', 'kind' => 'index', 'reason' => 'Business directory.']]]]);
    CompanyQualifier::fake([verdict(90), verdict(30)]);

    Http::fake([
        '*/search*' => Http::response(['results' => [
            ['url' => 'https://pagesdor.be/friteries/namur', 'title' => 'Friteries à Namur', 'content' => 'Annuaire'],
        ]]),
        '*/robots.txt' => Http::response('', 404),
        'https://pagesdor.be/friteries/namur' => Http::response(
            '<html lang="fr"><body><script type="application/ld+json">'
            .json_encode(['@type' => 'Restaurant', 'name' => 'Chez Marcel', 'url' => 'https://chez-marcel.test', 'telephone' => '+3281223344'])
            .'</script></body></html>'
        ),
        'https://chez-marcel.test/' => Http::response(page()),
        'https://pagesdor.be/' => Http::response(page('Annuaire des commerces belges.')),
    ]);

    $this->artisan('eveil:discover-companies')->assertSuccessful();

    expect(Company::pluck('domain')->sort()->values()->all())
        ->toBe(['chez-marcel.test', 'pagesdor.be'])
        ->and(KnownHost::query()->firstWhere('host', 'pagesdor.be')->kind)->toBe(HostKind::Index);
});

it('spends the query budget across both sources, not all of it on the first', function () {
    // Overpass rate-limits by IP and answers 429 when its slots are busy. Run
    // every map probe before the first web query and a busy Overpass takes the
    // whole `max_queries` budget with it: the run reports an empty market it
    // never actually searched.
    activeTargetProfile();
    app(Settings::class)->set('discovery', array_merge(app(Settings::class)->array('discovery'), ['max_queries' => 4]));
    config()->set('eveil.sources.overpass.retry_wait_ms', 0);

    DiscoveryPlanner::fake([plan(
        overpass: [overpassProbe('Namur'), overpassProbe('Charleroi'), overpassProbe('Liège'), overpassProbe('Mons')],
        web: [['query' => 'friterie namur', 'language' => 'fr', 'why' => 'Local.']],
    )]);
    ResultTriage::fake([['hosts' => [['host' => 'vraie-friterie.be', 'kind' => 'entity', 'reason' => 'One business.']]]]);
    CompanyQualifier::fake([verdict()]);

    Http::fake([
        '*/api/interpreter' => Http::response('rate limited', 429),
        '*/search*' => Http::response(['results' => [
            ['url' => 'https://vraie-friterie.be', 'title' => 'Vraie Friterie'],
        ]]),
        '*/robots.txt' => Http::response('', 404),
        'https://vraie-friterie.be/' => Http::response(page()),
    ]);

    $this->artisan('eveil:discover-companies')->assertSuccessful();

    expect(Company::sole()->source)->toBe('web_search');
});

it('waits out a busy Overpass instead of reporting an empty area', function (int $status) {
    activeTargetProfile();
    config()->set('eveil.sources.overpass.retry_wait_ms', 0);

    DiscoveryPlanner::fake([plan(overpass: [overpassProbe()])]);
    CompanyQualifier::fake([verdict()]);

    Http::fake([
        '*/api/interpreter' => Http::sequence()
            // 429: every slot taken. 504: the gateway gave up on a query still
            // running. Both mean "come back", neither means the area is empty.
            ->push('busy', $status)
            ->push(['elements' => [osmElement('Friterie du Centre', 'https://friterie-centre.be')]]),
        '*/robots.txt' => Http::response('', 404),
        'https://friterie-centre.be/' => Http::response(page()),
    ]);

    $this->artisan('eveil:discover-companies')->assertSuccessful();

    expect(Company::sole()->domain)->toBe('friterie-centre.be')
        ->and(DiscoveryRun::sole()->stats['source_failures'])->toBe([]);
})->with([429, 504]);

it('keeps a business with no site of its own when the listing published an address', function () {
    // The segment nobody else is calling. A search engine can never surface
    // these: they have nothing to rank, so the directory line is both how
    // they are found and the only place they publish an address.
    activeTargetProfile();

    DiscoveryPlanner::fake([plan(web: [['query' => 'friterie namur', 'language' => 'fr', 'why' => 'Local.']])]);
    ResultTriage::fake([['hosts' => [['host' => 'annuaire.test', 'kind' => 'index', 'reason' => 'Lists businesses.']]]]);
    CompanyQualifier::fake([verdict(75), verdict(20)]);

    Http::fake([
        '*/search*' => Http::response(['results' => [
            ['url' => 'https://annuaire.test/friteries/namur', 'title' => 'Friteries à Namur', 'content' => 'Annuaire'],
        ]]),
        '*/robots.txt' => Http::response('', 404),
        'https://annuaire.test/friteries/namur' => Http::response(
            '<html lang="fr"><body><script type="application/ld+json">'
            .json_encode([
                ['@type' => 'Restaurant', 'name' => 'Chez Marcel', 'email' => 'marcel@chez-marcel.test', 'telephone' => '+3281223344'],
                // Phone only: nothing to write to, so it is counted and left alone
                // rather than paid for with a model call.
                ['@type' => 'Restaurant', 'name' => 'Friterie Sans Mail', 'telephone' => '+3281556677'],
            ])
            .'</script></body></html>'
        ),
        'https://annuaire.test/' => Http::response(page('Annuaire des commerces.')),
    ]);

    $this->artisan('eveil:discover-companies')->assertSuccessful();

    $marcel = Company::query()->firstWhere('name', 'Chez Marcel');

    expect(Company::query()->pluck('name')->contains('Friterie Sans Mail'))->toBeFalse()
        ->and(Company::query()->whereNull('domain')->count())->toBe(1)
        ->and($marcel->domain)->toBeNull()
        ->and($marcel->website)->toBeNull()
        ->and($marcel->facts['email'])->toBe('marcel@chez-marcel.test');
});

it('does not discover the same site-less business twice', function () {
    // With no domain the name is the whole dedupe key, and a second run reads
    // the very same listing page.
    activeTargetProfile();

    DiscoveryPlanner::fake([
        plan(web: [['query' => 'friterie namur', 'language' => 'fr', 'why' => 'Local.']]),
        plan(web: [['query' => 'friterie namur', 'language' => 'fr', 'why' => 'Local.']]),
    ]);
    ResultTriage::fake([['hosts' => [['host' => 'annuaire.test', 'kind' => 'index', 'reason' => 'Lists businesses.']]]]);
    CompanyQualifier::fake([verdict(75), verdict(20)]);

    Http::fake([
        '*/search*' => Http::response(['results' => [
            ['url' => 'https://annuaire.test/friteries/namur', 'title' => 'Friteries à Namur', 'content' => 'Annuaire'],
        ]]),
        '*/robots.txt' => Http::response('', 404),
        'https://annuaire.test/friteries/namur' => Http::response(
            '<html lang="fr"><body><script type="application/ld+json">'
            .json_encode(['@type' => 'Restaurant', 'name' => 'Chez Marcel', 'email' => 'marcel@chez-marcel.test'])
            .'</script></body></html>'
        ),
        'https://annuaire.test/' => Http::response(page('Annuaire des commerces.')),
    ]);

    $this->artisan('eveil:discover-companies')->assertSuccessful();
    $this->artisan('eveil:discover-companies')->assertSuccessful();

    expect(Company::query()->whereNull('domain')->count())->toBe(1);
});
