<?php

use App\Ai\Agents\ResultTriage;
use App\Enums\HarvestStatus;
use App\Enums\HostKind;
use App\Models\KnownHost;
use App\Models\Project;
use App\Services\Discovery\Harvest;
use App\Services\Discovery\HostRegistry;
use Illuminate\Support\Collection;

/**
 * Enumerating the world's aggregators by hand is hopeless, so the model judges
 * a host once and the answer is kept for every future run of every project.
 */
function triaged(array $hosts): array
{
    return ['hosts' => collect($hosts)
        ->map(fn (string $kind, string $host): array => ['host' => $host, 'kind' => $kind, 'reason' => 'Because.'])
        ->values()
        ->all()];
}

function classify(array $urls, ?Project $project = null): array
{
    return app(HostRegistry::class)->classify(new Collection($urls), $project ?? Project::factory()->create());
}

it('asks the model about an unknown host and remembers the answer', function () {
    ResultTriage::fake([triaged(['annuaire.test' => 'index', 'marcel.test' => 'entity'])]);

    $verdicts = classify(['https://annuaire.test/friteries', 'https://marcel.test/']);

    expect($verdicts['annuaire.test'])->toBe(HostKind::Index)
        ->and($verdicts['marcel.test'])->toBe(HostKind::Entity)
        ->and(KnownHost::query()->firstWhere('host', 'annuaire.test')->kind)->toBe(HostKind::Index);
});

it('never asks twice about the same host, in any project', function () {
    ResultTriage::fake([triaged(['annuaire.test' => 'index'])]);

    classify(['https://annuaire.test/friteries']);

    // A different project entirely: the registry is instance-wide because
    // "this host lists businesses" is a fact about the web, not client data.
    ResultTriage::fake()->preventStrayPrompts();

    expect(classify(['https://annuaire.test/pizzerias'], Project::factory()->create())['annuaire.test'])
        ->toBe(HostKind::Index);
});

it('answers for the certainties without spending a token', function () {
    ResultTriage::fake()->preventStrayPrompts();

    $verdicts = classify([
        'https://www.facebook.com/marcel',
        'https://fr.wikipedia.org/wiki/Friterie',
        'https://news.ycombinator.com/item?id=1',
    ]);

    expect($verdicts['facebook.com'])->toBe(HostKind::Social)
        // Keyed on the host as it appeared, and the floor matches on a
        // substring — so a language subdomain is caught without its own entry.
        ->and($verdicts['fr.wikipedia.org'])->toBe(HostKind::Noise)
        ->and($verdicts['news.ycombinator.com'])->toBe(HostKind::Noise)
        // The floor is not a cache — nothing is written for it.
        ->and(KnownHost::count())->toBe(0);
});

it('re-judges a verdict that has gone stale', function () {
    // Sites change CDN configuration and directories die, so a verdict must
    // not be a life sentence.
    KnownHost::factory()->stale()->create(['host' => 'annuaire.test', 'kind' => HostKind::Noise]);

    ResultTriage::fake([triaged(['annuaire.test' => 'index'])]);

    expect(classify(['https://annuaire.test/friteries'])['annuaire.test'])->toBe(HostKind::Index)
        ->and(KnownHost::query()->firstWhere('host', 'annuaire.test')->kind)->toBe(HostKind::Index);
});

it('never re-judges or overwrites a row a human locked', function () {
    // The superadmin screen is the escape hatch for a verdict the model got
    // wrong — and a wrong verdict is invisible-forever for every project.
    KnownHost::factory()->stale()->create([
        'host' => 'marcel.test',
        'kind' => HostKind::Entity,
        'is_locked' => true,
    ]);

    ResultTriage::fake()->preventStrayPrompts();

    expect(classify(['https://marcel.test/'])['marcel.test'])->toBe(HostKind::Entity);
});

it('treats the batch as ordinary sites when triage blows up', function () {
    ResultTriage::fake(fn () => throw new RuntimeException('provider exploded'));

    expect(classify(['https://marcel.test/'])['marcel.test'])->toBe(HostKind::Entity)
        // Nothing cached, so the next run tries again rather than inheriting a
        // verdict we never actually made.
        ->and(KnownHost::count())->toBe(0);
});

it('records what a harvest did so a blocked host is never paid for twice', function () {
    $host = KnownHost::factory()->index()->create(['host' => 'annuaire.test']);

    app(HostRegistry::class)->recordHarvest('annuaire.test', new Harvest(new Collection));

    expect($host->fresh()->harvest_status)->toBe(HarvestStatus::Blocked)
        ->and($host->fresh()->isWorthHarvesting())->toBeFalse();
});

it('leaves a locked row alone even when recording a harvest', function () {
    $host = KnownHost::factory()->index()->create(['host' => 'annuaire.test', 'is_locked' => true]);

    app(HostRegistry::class)->recordHarvest('annuaire.test', new Harvest(new Collection));

    expect($host->fresh()->harvest_status)->toBeNull();
});
