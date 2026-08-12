<?php

use App\Ai\Agents\ListingExtractor;
use App\Discovery\ListingHarvester;
use App\Models\AgentRun;
use App\Models\Project;
use Illuminate\Support\Facades\Http;

function listingPage(string $body): string
{
    return "<html lang=\"fr\"><head><title>Annuaire</title></head><body>{$body}</body></html>";
}

function jsonLd(array $data): string
{
    return '<script type="application/ld+json">'.json_encode($data).'</script>';
}

function friterie(string $name, array $extra = []): array
{
    return array_merge([
        '@type' => 'Restaurant',
        'name' => $name,
        'telephone' => '+32 81 22 33 44',
        'address' => ['@type' => 'PostalAddress', 'streetAddress' => 'Rue Haute 4', 'addressLocality' => 'Namur'],
    ], $extra);
}

beforeEach(function () {
    Http::fake(['*/robots.txt' => Http::response('', 404)]);
});

it('reads the businesses out of json-ld without calling a model', function () {
    Http::fake([
        'https://annuaire.test/friteries' => Http::response(listingPage(jsonLd([
            '@type' => 'ItemList',
            'itemListElement' => [
                ['@type' => 'ListItem', 'item' => friterie('Chez Marcel', ['url' => 'https://chez-marcel.be'])],
                ['@type' => 'ListItem', 'item' => friterie('Belle Frite')],
            ],
        ]))),
        '*/robots.txt' => Http::response('', 404),
    ]);

    ListingExtractor::fake()->preventStrayPrompts();

    $harvest = app(ListingHarvester::class)->harvest('https://annuaire.test/friteries');

    expect($harvest->candidates)->toHaveCount(2)
        ->and($harvest->modes)->toBe(['jsonld'])
        ->and($harvest->usedAgent())->toBeFalse();

    $marcel = $harvest->candidates->first();

    expect($marcel->name)->toBe('Chez Marcel')
        ->and($marcel->website)->toBe('https://chez-marcel.be/')
        ->and($marcel->facts['address'])->toBe('Rue Haute 4, Namur')
        ->and($marcel->facts['directory'])->toBe('annuaire.test');
});

it('keeps the business that publishes no website, which is the whole point', function () {
    // ADR-033: a search engine never surfaces these. They are counted rather
    // than dropped, because `companies.domain` is NOT NULL and cannot hold them yet.
    Http::fake([
        'https://annuaire.test/friteries' => Http::response(listingPage(jsonLd([
            friterie('Belle Frite'),
            friterie('Chez Marcel', ['url' => 'https://chez-marcel.be']),
        ]))),
        '*/robots.txt' => Http::response('', 404),
    ]);

    $harvest = app(ListingHarvester::class)->harvest('https://annuaire.test/friteries');

    expect($harvest->withoutWebsite())->toBe(1)
        ->and($harvest->candidates->firstWhere('name', 'Belle Frite')->website)->toBeNull()
        // Its directory page is still recorded: it is the only way back to it.
        ->and($harvest->candidates->firstWhere('name', 'Belle Frite')->sourceUrl)
        ->toBe('https://annuaire.test/friteries');
});

it('treats a url on the directory own host as a detail page, not a website', function () {
    Http::fake([
        'https://annuaire.test/friteries' => Http::response(listingPage(jsonLd([
            friterie('Chez Marcel', ['url' => 'https://annuaire.test/friterie/chez-marcel-4412']),
        ]))),
        '*/robots.txt' => Http::response('', 404),
    ]);

    $harvest = app(ListingHarvester::class)->harvest('https://annuaire.test/friteries');

    expect($harvest->candidates->first()->website)->toBeNull()
        ->and($harvest->candidates->first()->sourceUrl)->toBe('https://annuaire.test/friterie/chez-marcel-4412');
});

it('follows pagination and dedupes across pages', function () {
    Http::fake([
        'https://annuaire.test/friteries' => Http::response(listingPage(
            jsonLd([friterie('Chez Marcel'), friterie('Belle Frite')])
            .'<nav><a href="?page=2">Page suivante</a></nav>'
        )),
        'https://annuaire.test/friteries?page=2' => Http::response(listingPage(
            // Marcel again — listed in two categories — plus one new.
            jsonLd([friterie('Chez Marcel'), friterie('Fritkot du Coin')])
        )),
        '*/robots.txt' => Http::response('', 404),
    ]);

    $harvest = app(ListingHarvester::class)->harvest('https://annuaire.test/friteries');

    expect($harvest->candidates->pluck('name')->all())
        ->toBe(['Chez Marcel', 'Belle Frite', 'Fritkot du Coin'])
        ->and($harvest->pages)->toHaveCount(2);
});

it('stops on the page budget rather than reading a directory forever', function () {
    Http::fake([
        'https://annuaire.test/friteries*' => Http::response(listingPage(
            jsonLd([friterie('Chez Marcel')]).'<link rel="next" href="/friteries?page=99">'
        )),
        '*/robots.txt' => Http::response('', 404),
    ]);

    $harvest = app(ListingHarvester::class)->harvest('https://annuaire.test/friteries', null, maxPages: 2);

    expect($harvest->pages)->toHaveCount(2)
        ->and($harvest->stoppedBecause)->toBe('page budget');
});

it('stops when pagination points back at a page already read', function () {
    Http::fake([
        'https://annuaire.test/friteries' => Http::response(listingPage(
            jsonLd([friterie('Chez Marcel')]).'<a rel="next" href="/friteries?page=2">2</a>'
        )),
        'https://annuaire.test/friteries?page=2' => Http::response(listingPage(
            jsonLd([friterie('Belle Frite')]).'<a rel="next" href="/friteries?page=2">2</a>'
        )),
        '*/robots.txt' => Http::response('', 404),
    ]);

    $harvest = app(ListingHarvester::class)->harvest('https://annuaire.test/friteries');

    expect($harvest->stoppedBecause)->toBe('pagination loops')
        ->and($harvest->candidates)->toHaveCount(2);
});

it('falls back to the model only when there is no json-ld', function () {
    Http::fake([
        'https://annuaire.test/friteries' => Http::response(listingPage(
            '<ul><li><a href="/friterie/chez-marcel-4412">Chez Marcel</a></li></ul>'
        )),
        '*/robots.txt' => Http::response('', 404),
    ]);

    ListingExtractor::fake([[
        'businesses' => [[
            'name' => 'Chez Marcel',
            'website' => 'chez-marcel.be',
            'detail_url' => '/friterie/chez-marcel-4412',
            'email' => 'info@chez-marcel.be',
            'phone' => '',
            'address' => '',
        ]],
    ]]);

    $harvest = app(ListingHarvester::class)->harvest('https://annuaire.test/friteries', Project::factory()->create());

    expect($harvest->modes)->toBe(['llm'])
        ->and($harvest->usedAgent())->toBeTrue();

    $marcel = $harvest->candidates->first();

    // A bare domain from the model is given a scheme rather than dropped.
    expect($marcel->website)->toBe('https://chez-marcel.be/')
        ->and($marcel->facts['email'])->toBe('info@chez-marcel.be')
        ->and($marcel->facts['detail_url'])->toBe('https://annuaire.test/friterie/chez-marcel-4412');
});

it('refuses a website the model borrowed from the directory itself', function () {
    Http::fake([
        'https://annuaire.test/friteries' => Http::response(listingPage('<p>Chez Marcel</p>')),
        '*/robots.txt' => Http::response('', 404),
    ]);

    ListingExtractor::fake([[
        'businesses' => [[
            'name' => 'Chez Marcel',
            'website' => 'https://annuaire.test/friterie/chez-marcel-4412',
            'detail_url' => '',
            'email' => '',
            'phone' => '',
            'address' => '',
        ]],
    ]]);

    $harvest = app(ListingHarvester::class)->harvest('https://annuaire.test/friteries', Project::factory()->create());

    expect($harvest->candidates->first()->website)->toBeNull();
});

it('never calls a model when no project is given', function () {
    Http::fake([
        'https://annuaire.test/friteries' => Http::response(listingPage('<p>Rien de structuré</p>')),
        '*/robots.txt' => Http::response('', 404),
    ]);

    ListingExtractor::fake()->preventStrayPrompts();

    $harvest = app(ListingHarvester::class)->harvest('https://annuaire.test/friteries');

    expect($harvest->candidates)->toBeEmpty()
        ->and($harvest->modes)->toBe(['none']);
});

it('reports a page it could not read instead of returning a silent empty', function () {
    Http::fake([
        'https://annuaire.test/friteries' => Http::response('nope', 500),
        '*/robots.txt' => Http::response('', 404),
    ]);

    $harvest = app(ListingHarvester::class)->harvest('https://annuaire.test/friteries');

    expect($harvest->candidates)->toBeEmpty()
        ->and($harvest->stoppedBecause)->toBe('page could not be read');
});

it('pays for an extraction once, not on every re-run', function () {
    // The LLM path is the normal path, not a fallback: nobody publishes JSON-LD.
    // `crawled_pages` caches the fetch, not the model call, so without this a
    // second harvest of the same directory re-bills every page.
    Http::fake([
        'https://annuaire.test/friteries' => Http::response(listingPage('<p>Chez Marcel, Rue Haute 4</p>')),
        '*/robots.txt' => Http::response('', 404),
    ]);

    ListingExtractor::fake([
        ['businesses' => [['name' => 'Chez Marcel', 'website' => '', 'detail_url' => '', 'email' => '', 'phone' => '', 'address' => '']]],
    ]);

    $project = Project::factory()->create();

    app(ListingHarvester::class)->harvest('https://annuaire.test/friteries', $project);
    $again = app(ListingHarvester::class)->harvest('https://annuaire.test/friteries', $project);

    // A second prompt would exhaust the single faked response and blow up.
    expect($again->candidates->pluck('name')->all())->toBe(['Chez Marcel'])
        ->and(AgentRun::count())->toBe(1);
});
