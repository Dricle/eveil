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

beforeEach(fn () => config()->set('eveil.crawl.delay_ms', 0));

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

it('throws away directories and platforms returned by the search engine', function () {
    activeTargetProfile();
    DiscoveryPlanner::fake([plan(web: [['query' => 'friterie', 'language' => 'fr', 'why' => '...']])]);
    CompanyQualifier::fake([verdict()]);

    Http::fake([
        '*/search*' => Http::response(['results' => [
            ['url' => 'https://www.tripadvisor.be/friteries', 'title' => 'Top friteries'],
            ['url' => 'https://fr.wikipedia.org/wiki/Friterie', 'title' => 'Friterie'],
            ['url' => 'https://deliveroo.be/fr/restaurants', 'title' => 'Livraison'],
            ['url' => 'https://vraie-friterie.be', 'title' => 'Vraie Friterie'],
        ]]),
        '*/robots.txt' => Http::response('', 404),
        '*' => Http::response(page()),
    ]);

    $this->artisan('eveil:discover-companies')->assertSuccessful();

    // Directories are how you find companies, not companies you can sell to.
    expect(Company::pluck('domain')->all())->toBe(['vraie-friterie.be']);
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

    // A probe on "Charleroi" alone also returns Charleroi, Pennsylvania — the
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
    // would make that buyer unserviceable — the host would be scraped for its
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
