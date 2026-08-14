<?php

use App\Ai\Agents\ResultTriage;
use App\Enums\HarvestStatus;
use App\Enums\HostKind;
use App\Models\KnownHost;
use App\Models\Project;
use App\Services\Discovery\Harvest;
use App\Services\Discovery\HostRegistry;
use Database\Seeders\KnownHostSeeder;
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
    // These used to be a const inside HostRegistry, consulted before the table.
    // A hardcoded list shadowing a table is that table minus the ability to
    // edit it, so they are locked rows now.
    $this->seed(KnownHostSeeder::class);
    ResultTriage::fake()->preventStrayPrompts();

    $verdicts = classify([
        'https://www.facebook.com/marcel',
        'https://fr.wikipedia.org/wiki/Friterie',
        'https://www.google.com/search?q=friterie',
    ]);

    expect($verdicts['facebook.com'])->toBe(HostKind::Social)
        // Answered from the `wikipedia.org` row: a language subdomain resolves
        // through its parent domain, so a site is not judged once per locale.
        ->and($verdicts['fr.wikipedia.org'])->toBe(HostKind::Other)
        ->and($verdicts['google.com'])->toBe(HostKind::Other);
});

it('resolves a subdomain through its parent domain', function () {
    KnownHost::factory()->index()->create(['host' => 'pagesdor.be']);
    ResultTriage::fake()->preventStrayPrompts();

    expect(classify(['https://nl.pagesdor.be/frituren'])['nl.pagesdor.be'])->toBe(HostKind::Index);
});

it('locks the certainties so no model ever overwrites one', function () {
    $this->seed(KnownHostSeeder::class);

    $facebook = KnownHost::query()->firstWhere('host', 'facebook.com');

    expect($facebook->is_locked)->toBeTrue()
        // Locked means authoritative forever: no expiry, no re-judging.
        ->and($facebook->isAuthoritative())->toBeTrue()
        // The learned rows are NOT locked — a seeded guess is not a decision.
        ->and(KnownHost::query()->firstWhere('host', 'producthunt.com')->is_locked)->toBeFalse();
});

it('leaves a host to the model when its kind depends on nothing but structure', function () {
    // A job board looks like noise until you notice a recruitment agency hunts
    // companies that are hiring, and code hosting looks like noise until a
    // developer-tool profile needs it. Neither belongs in the floor: the
    // verdict is structural, so both are indexes for everyone.
    ResultTriage::fake([triaged(['indeed.test' => 'index', 'codehost.test' => 'index'])]);

    $verdicts = classify(['https://indeed.test/jobs', 'https://codehost.test/orgs']);

    expect($verdicts['indeed.test'])->toBe(HostKind::Index)
        ->and($verdicts['codehost.test'])->toBe(HostKind::Index);
});

it('ships a seed whose verdicts hold for every kind of buyer', function () {
    $this->seed(KnownHostSeeder::class);

    $kindOf = fn (string $host): HostKind => KnownHost::query()->firstWhere('host', $host)->kind;

    expect($kindOf('indeed.com'))->toBe(HostKind::Index)
        ->and($kindOf('github.com'))->toBe(HostKind::Index)
        ->and($kindOf('deliveroo.com'))->toBe(HostKind::Index)
        ->and($kindOf('amazon.com'))->toBe(HostKind::Index)
        // Not locked: a shipped guess is not a human decision, so it expires
        // like any other verdict.
        ->and(KnownHost::query()->firstWhere('host', 'indeed.com')->is_locked)->toBeFalse();
});

it('re-judges a verdict that has gone stale', function () {
    // Sites change CDN configuration and directories die, so a verdict must
    // not be a life sentence.
    KnownHost::factory()->stale()->create(['host' => 'annuaire.test', 'kind' => HostKind::Other]);

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
