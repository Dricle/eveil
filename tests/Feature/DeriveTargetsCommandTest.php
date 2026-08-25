<?php

use App\Ai\Agents\TargetProfileDeriver;
use App\Enums\TargetProfileSource;
use App\Enums\TargetProfileType;
use App\Models\AgentRun;
use App\Models\Project;
use App\Models\TargetProfile;

function profile(string $name): array
{
    return [
        'name' => $name,
        'rationale' => 'They pay platform commissions today.',
        'sectors' => ['friteries', 'pizzerias'],
        'company_size' => '1 to 3 locations',
        'geography' => ['BE-WAL'],
        'job_titles' => ['gérant'],
        'technologies' => [],
        'trigger_signals' => ['recently opened'],
        'search_queries' => ['friterie namur', 'pizzeria liege livraison'],
        'estimated_market_size' => 'Roughly 900 in Wallonia.',
        'confidence' => 82,
    ];
}

function projectWithKnowledgeBase(): Project
{
    return Project::factory()->create([
        'name' => 'RestoGo',
        'knowledge_base' => ['what_it_does' => 'Commission-free ordering for restaurants.'],
    ]);
}

it('derives and stores the profiles', function () {
    $project = projectWithKnowledgeBase();
    TargetProfileDeriver::fake([['profiles' => [profile('Friteries wallonnes'), profile('Pizzerias bruxelloises')]]]);

    $this->artisan('eveil:derive-targets')->assertSuccessful();

    expect(TargetProfile::count())->toBe(2);

    $first = TargetProfile::query()->orderBy('id')->first();

    expect($first->name)->toBe('Friteries wallonnes')
        ->and($first->source)->toBe(TargetProfileSource::Agent)
        ->and($first->is_active)->toBeTrue()
        ->and($first->project_id)->toBe($project->id)
        // The name is the handle; everything else is searchable criteria.
        ->and($first->criteria)->not->toHaveKey('name')
        ->and($first->criteria['search_queries'])->toContain('friterie namur');
});

it('meters the call', function () {
    projectWithKnowledgeBase();
    TargetProfileDeriver::fake([['profiles' => [profile('Friteries wallonnes')]]]);

    $this->artisan('eveil:derive-targets')->assertSuccessful();

    expect(AgentRun::sole()->agent)->toBe('target-profile-deriver');
});

it('refuses to run before the site has been analysed', function () {
    Project::factory()->create(['knowledge_base' => null]);

    $this->artisan('eveil:derive-targets')
        ->expectsOutputToContain('eveil:analyze')
        ->assertFailed();

    expect(TargetProfile::count())->toBe(0);
});

it('will not derive twice without --fresh', function () {
    projectWithKnowledgeBase();
    TargetProfileDeriver::fake([['profiles' => [profile('Friteries wallonnes')]]]);

    $this->artisan('eveil:derive-targets')->assertSuccessful();
    $this->artisan('eveil:derive-targets')->assertSuccessful();

    expect(TargetProfile::count())->toBe(1)
        ->and(AgentRun::count())->toBe(1);
});

it('replaces its own profiles but never the ones a human wrote', function () {
    $project = projectWithKnowledgeBase();
    TargetProfileDeriver::fake([
        ['profiles' => [profile('Première passe')]],
        ['profiles' => [profile('Deuxième passe')]],
    ]);

    $this->artisan('eveil:derive-targets')->assertSuccessful();

    $handWritten = TargetProfile::create([
        'project_id' => $project->id,
        'name' => 'Écrit à la main',
        'criteria' => ['sectors' => ['boulangeries']],
        'source' => TargetProfileSource::Human,
    ]);

    $this->artisan('eveil:derive-targets', ['--fresh' => true])->assertSuccessful();

    // The user can CRUD profiles freely, so a re-derivation may only
    // throw away what the agent itself produced.
    expect(TargetProfile::pluck('name')->sort()->values()->all())->toBe(['Deuxième passe', 'Écrit à la main'])
        ->and(TargetProfile::find($handWritten->id))->not->toBeNull();
});

it('fails when the agent returns no profile at all', function () {
    projectWithKnowledgeBase();
    TargetProfileDeriver::fake([['profiles' => []]]);

    $this->artisan('eveil:derive-targets')
        ->expectsOutputToContain('too thin')
        ->assertFailed();
});

it('asks which project when there are several', function () {
    projectWithKnowledgeBase();
    projectWithKnowledgeBase();

    $this->artisan('eveil:derive-targets')
        ->expectsOutputToContain('Several projects')
        ->assertFailed();
});

it('finds a project by name, url or id', function () {
    $project = projectWithKnowledgeBase();
    projectWithKnowledgeBase();
    TargetProfileDeriver::fake([['profiles' => [profile('Friteries wallonnes')]], ['profiles' => [profile('Autre')]]]);

    $this->artisan('eveil:derive-targets', ['project' => 'RestoGo'])->assertSuccessful();
    $this->artisan('eveil:derive-targets', ['project' => (string) $project->id, '--fresh' => true])->assertSuccessful();

    expect(TargetProfile::where('project_id', $project->id)->count())->toBe(1);
});

it('says so when no project matches', function () {
    $this->artisan('eveil:derive-targets', ['project' => 'nope'])
        ->expectsOutputToContain('No project matches')
        ->assertFailed();
});

it('stores a low-confidence profile inactive, and a confident one active', function () {
    projectWithKnowledgeBase();

    TargetProfileDeriver::fake([['profiles' => [
        [...profile('Friteries wallonnes'), 'confidence' => 20],
        [...profile('Pizzerias bruxelloises'), 'confidence' => 60],
    ]]]);

    $this->artisan('eveil:derive-targets')->assertSuccessful();

    $shaky = TargetProfile::query()->firstWhere('name', 'Friteries wallonnes');
    $confident = TargetProfile::query()->firstWhere('name', 'Pizzerias bruxelloises');

    expect($shaky->is_active)->toBeFalse()
        ->and($confident->is_active)->toBeTrue();
});

it('derives partner profiles alongside customers, with the angles the email will open on', function () {
    // A market of businesses that publish a phone and no address is a right
    // profile nobody can be written to. Whoever already visits or invoices them
    // is reachable, and one of them carries hundreds of the buyers.
    projectWithKnowledgeBase();

    TargetProfileDeriver::fake([['profiles' => [
        profile('Friteries wallonnes'),
        [
            ...profile('Grossistes en surgelés'),
            'type' => 'partner',
            'access_angle' => 'Their reps deliver to 3,000 friteries every week.',
            'partnership_angle' => 'A revenue share on every restaurant that signs up.',
        ],
    ]]]);

    $this->artisan('eveil:derive-targets')->assertSuccessful();

    $customer = TargetProfile::query()->firstWhere('name', 'Friteries wallonnes');
    $partner = TargetProfile::query()->firstWhere('name', 'Grossistes en surgelés');

    expect($customer->type)->toBe(TargetProfileType::Customer)
        ->and($partner->type)->toBe(TargetProfileType::Partner)
        // The kind is queryable on the row; the angles are criteria like the rest.
        ->and($partner->criteria)->not->toHaveKey('type')
        ->and($partner->criteria['access_angle'])->toBe('Their reps deliver to 3,000 friteries every week.')
        ->and($partner->criteria['partnership_angle'])->toBe('A revenue share on every restaurant that signs up.');
});
