<?php

use App\Ai\Agents\ContactPageFinder;
use App\Enums\PathHintKind;
use App\Models\PathHint;
use App\Models\Project;
use App\Services\Discovery\PathHints;
use App\Support\HtmlText;
use App\Support\ParsedPage;

/**
 * The list of the world's words for "contact us" cannot be finished by hand, so
 * it is learned: seeded fragments first, a model when they miss, and whatever
 * the model picked written back for everyone.
 */
function homepage(string $links): ParsedPage
{
    return (new HtmlText)->parse("<html><body>{$links}</body></html>", 'https://marcel.test/');
}

function picked(string $links, ?Project $project = null, int $limit = 4): array
{
    return app(PathHints::class)
        ->pick(homepage($links), PathHintKind::Contact, $project ?? Project::factory()->create(), $limit)
        ->all();
}

it('follows a known fragment without spending a token', function () {
    PathHint::factory()->create(['token' => 'contact']);
    ContactPageFinder::fake()->preventStrayPrompts();

    expect(picked('<a href="/contact">Contact</a><a href="/blog/x">Blog</a>'))
        ->toBe(['https://marcel.test/contact']);
});

it('asks the model when no fragment matches, and remembers the answer', function () {
    // A market whose word for "contact us" we have not met. This is the whole
    // point: a hardcoded list silently returns nothing here.
    PathHint::factory()->create(['token' => 'contact']);

    ContactPageFinder::fake([['links' => [
        ['url' => '/chi-siamo', 'why' => 'About us in Italian.'],
    ]]]);

    expect(picked('<a href="/chi-siamo">Chi siamo</a><a href="/prodotti">Prodotti</a>'))
        ->toBe(['https://marcel.test/chi-siamo']);

    // Learned for every project on the instance, so the next Italian site is free.
    expect(PathHint::query()->where('token', 'chi-siamo')->exists())->toBeTrue();

    ContactPageFinder::fake()->preventStrayPrompts();

    expect(picked('<a href="/chi-siamo">Chi siamo</a>', Project::factory()->create()))
        ->toBe(['https://marcel.test/chi-siamo']);
});

it('learns the last meaningful segment, not the whole path', function () {
    ContactPageFinder::fake([['links' => [
        ['url' => '/nl/over-ons', 'why' => 'About us.'],
        ['url' => '/x', 'why' => 'Too short to teach anything.'],
        ['url' => '/pages/1234', 'why' => 'Numeric, teaches nothing.'],
        ['url' => '/kontakty.html', 'why' => 'With an extension.'],
    ]]]);

    picked('<a href="/nl/over-ons">Over ons</a>');

    expect(PathHint::query()->pluck('token')->sort()->values()->all())
        ->toBe(['kontakty', 'over-ons']);
});

it('refuses a link the model borrowed from another site', function () {
    ContactPageFinder::fake([['links' => [
        ['url' => 'https://elsewhere.test/contact', 'why' => 'Not this site.'],
    ]]]);

    expect(picked('<a href="/quelque-chose">Nous</a>'))->toBe([])
        ->and(PathHint::count())->toBe(0);
});

it('ranks a fragment that keeps paying off above one that worked once', function () {
    PathHint::factory()->create(['token' => 'legal', 'hits' => 0]);
    PathHint::factory()->create(['token' => 'contact', 'hits' => 0]);
    ContactPageFinder::fake()->preventStrayPrompts();

    app(PathHints::class)->record('https://marcel.test/contact', PathHintKind::Contact, paidOff: true);

    expect(picked('<a href="/legal">Legal</a><a href="/contact">Contact</a>', limit: 1))
        ->toBe(['https://marcel.test/contact'])
        ->and(PathHint::query()->firstWhere('token', 'contact')->hits)->toBe(1);
});

it('starts from nothing and teaches itself', function () {
    // No seeder, no const: the table is empty on a fresh install and the first
    // site pays a fraction of a cent to fill it, once, for every project.
    expect(PathHint::count())->toBe(0);

    ContactPageFinder::fake([['links' => [['url' => '/contact', 'why' => 'Contact page.']]]]);

    picked('<a href="/contact">Contact</a>');

    ContactPageFinder::fake()->preventStrayPrompts();

    expect(picked('<a href="/contact">Contact</a>', Project::factory()->create()))
        ->toBe(['https://marcel.test/contact']);
});

it('retires a fragment that keeps choosing pages and never delivering', function () {
    // The guard against `learn()` picking up something far too generic. No
    // stop-list of banned words: that would be another hardcoded list. The
    // ratio simply catches a fragment that cannot deliver.
    $generic = PathHint::factory()->create(['token' => 'informations']);
    $good = PathHint::factory()->create(['token' => 'contact']);

    for ($i = 0; $i < 10; $i++) {
        app(PathHints::class)->record('https://marcel.test/informations', PathHintKind::Contact, paidOff: false);
        app(PathHints::class)->record('https://marcel.test/contact', PathHintKind::Contact, paidOff: true);
    }

    expect(app(PathHints::class)->review(PathHintKind::Contact))->toBe(['informations'])
        ->and(PathHint::query()->find($generic->id))->toBeNull()
        ->and(PathHint::query()->find($good->id))->not->toBeNull();
});

it('judges nothing on thin evidence, and never a locked row', function () {
    $new = PathHint::factory()->create(['token' => 'nouveau']);
    $locked = PathHint::factory()->create(['token' => 'jamais-touche', 'is_locked' => true]);

    // Two bad attempts is not a verdict; a good fragment can start badly.
    app(PathHints::class)->record('https://marcel.test/nouveau', PathHintKind::Contact, paidOff: false);
    app(PathHints::class)->record('https://marcel.test/nouveau', PathHintKind::Contact, paidOff: false);

    for ($i = 0; $i < 10; $i++) {
        app(PathHints::class)->record('https://marcel.test/jamais-touche', PathHintKind::Contact, paidOff: false);
    }

    expect(app(PathHints::class)->review(PathHintKind::Contact))->toBe([])
        ->and(PathHint::query()->find($new->id))->not->toBeNull()
        ->and(PathHint::query()->find($locked->id))->not->toBeNull();
});

it('returns nothing rather than failing the run when the model blows up', function () {
    ContactPageFinder::fake(fn () => throw new RuntimeException('provider exploded'));

    expect(picked('<a href="/quelque-chose">Nous</a>'))->toBe([]);
});
