<?php

use App\Ai\Agents\TargetProfileDeriver;
use App\Enums\TargetProfileSource;
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
