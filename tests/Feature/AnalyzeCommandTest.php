<?php

use App\Ai\Agents\WebsiteAnalyst;
use App\Enums\AnalysisStatus;
use App\Models\AgentRun;
use App\Models\Organization;
use App\Models\Project;
use App\Models\ProjectAnalysis;
use App\Support\Settings;
use Illuminate\Support\Facades\Http;

function knowledgeBase(): array
{
    return [
        'what_it_does' => 'Sells widgets to small factories.',
        'who_it_is_for' => 'Operations managers in manufacturing.',
        'value_proposition' => 'Cheaper widgets, next-day delivery.',
        'positioning' => 'The affordable alternative to industrial suppliers.',
        'key_features' => ['Next-day delivery', 'Bulk pricing'],
        'pricing_model' => 'Per unit, volume discounts.',
        'competitors' => [],
        'proof_points' => ['300 factories served'],
        'language' => 'fr',
        'confidence' => 80,
        'gaps' => ['No mention of minimum order size.'],
    ];
}

function fakeSite(): void
{
    app(Settings::class)->set('crawl.delay_ms', 0);

    Http::fake([
        '*/robots.txt' => Http::response('', 404),
        'https://acme.test/' => Http::response(
            '<!doctype html><html lang="fr"><head><title>Acme</title></head>'
            .'<body><p>Acme vend des widgets.</p><a href="/about">A propos</a></body></html>'
        ),
        'https://acme.test/about' => Http::response(
            '<!doctype html><html lang="fr"><head><title>A propos</title></head>'
            .'<body><p>Fondee a Namur en 2019.</p></body></html>'
        ),
    ]);
}

it('creates the project, builds the knowledge base and records the run', function () {
    fakeSite();
    WebsiteAnalyst::fake([knowledgeBase()]);

    $this->artisan('eveil:analyze', ['url' => 'https://acme.test'])->assertSuccessful();

    $project = Project::sole();

    expect($project->name)->toBe('acme.test')
        ->and($project->url)->toBe('https://acme.test/')
        ->and($project->knowledge_base['what_it_does'])->toBe('Sells widgets to small factories.')
        ->and($project->default_language)->toBe('fr')
        ->and(ProjectAnalysis::sole()->status)->toBe(AnalysisStatus::Succeeded)
        ->and(AgentRun::sole()->project_id)->toBe($project->id);
});

it('creates the implicit organization on a fresh install', function () {
    fakeSite();
    WebsiteAnalyst::fake([knowledgeBase()]);

    $this->artisan('eveil:analyze', ['url' => 'https://acme.test'])->assertSuccessful();

    // Self-hosted single-user still gets an organization, so there is
    // one code path rather than two.
    expect(Organization::sole()->slug)->toBe('default')
        ->and(Project::sole()->organization_id)->toBe(Organization::sole()->id);
});

it('records which pages were read', function () {
    fakeSite();
    WebsiteAnalyst::fake([knowledgeBase()]);

    $this->artisan('eveil:analyze', ['url' => 'https://acme.test'])->assertSuccessful();

    expect(ProjectAnalysis::sole()->raw['pages'])->toHaveCount(2);
});

it('refuses to rebuild an existing knowledge base without --fresh', function () {
    fakeSite();
    WebsiteAnalyst::fake([knowledgeBase()]);

    $this->artisan('eveil:analyze', ['url' => 'https://acme.test'])->assertSuccessful();
    $this->artisan('eveil:analyze', ['url' => 'https://acme.test'])->assertSuccessful();

    expect(ProjectAnalysis::count())->toBe(1)
        ->and(Project::count())->toBe(1);
});

it('never overwrites a knowledge base the user edited', function () {
    fakeSite();
    WebsiteAnalyst::fake([knowledgeBase(), knowledgeBase()]);

    $this->artisan('eveil:analyze', ['url' => 'https://acme.test'])->assertSuccessful();

    Project::sole()->update([
        'knowledge_base' => ['what_it_does' => 'Corrected by hand.'],
        'knowledge_base_edited_by_user' => true,
    ]);

    $this->artisan('eveil:analyze', ['url' => 'https://acme.test', '--fresh' => true])->assertSuccessful();

    // Story 3.2: the user corrected us once and should not have to again. The
    // analysis is still recorded, it just does not clobber their edit.
    expect(Project::sole()->knowledge_base['what_it_does'])->toBe('Corrected by hand.')
        ->and(ProjectAnalysis::count())->toBe(2);
});

it('fails cleanly when nothing can be read', function () {
    app(Settings::class)->set('crawl.delay_ms', 0);
    Http::fake(['*' => Http::response('', 500)]);

    $this->artisan('eveil:analyze', ['url' => 'https://acme.test'])->assertFailed();

    expect(ProjectAnalysis::sole()->status)->toBe(AnalysisStatus::Failed)
        ->and(ProjectAnalysis::sole()->error)->toContain('unreachable');
});

it('rejects something that is not a url', function () {
    $this->artisan('eveil:analyze', ['url' => 'not-a-url'])->assertFailed();

    expect(Project::count())->toBe(0);
});

it('honours the page limit', function () {
    fakeSite();
    WebsiteAnalyst::fake([knowledgeBase()]);

    $this->artisan('eveil:analyze', ['url' => 'https://acme.test', '--pages' => 1])->assertSuccessful();

    expect(ProjectAnalysis::sole()->raw['pages'])->toHaveCount(1);
});

it('stops with a readable message when no provider key is configured', function () {
    config()->set('ai.providers.anthropic.key', null);

    $this->artisan('eveil:analyze', ['url' => 'https://acme.test'])
        ->expectsOutputToContain('ANTHROPIC_API_KEY')
        ->assertFailed();

    // Nothing was created: a missing key must not leave a half-built project.
    expect(Project::count())->toBe(0);
});
