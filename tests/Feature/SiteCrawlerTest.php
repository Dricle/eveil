<?php

use App\Discovery\SiteCrawler;
use App\Models\CrawledPage;
use Illuminate\Support\Facades\Http;

function html(string $body, string $lang = 'fr'): string
{
    return "<!doctype html><html lang=\"{$lang}\"><head><title>Page</title></head><body>{$body}</body></html>";
}

beforeEach(function () {
    config()->set('eveil.crawl.delay_ms', 0);
});

it('reads the homepage and follows the pages that carry product information', function () {
    Http::fake([
        '*/robots.txt' => Http::response('', 404),
        'https://acme.test/' => Http::response(html(
            '<h1>Acme</h1><a href="/blog/hello">Blog</a><a href="/pricing">Pricing</a><a href="/about">About</a>'
        )),
        'https://acme.test/pricing' => Http::response(html('<p>29 EUR per month</p>')),
        'https://acme.test/about' => Http::response(html('<p>Founded in Namur</p>')),
        'https://acme.test/blog/hello' => Http::response(html('<p>A blog post</p>')),
    ]);

    $pages = app(SiteCrawler::class)->crawl('https://acme.test', maxPages: 3);

    expect($pages->pluck('url')->all())->toBe([
        'https://acme.test/',
        'https://acme.test/about',
        'https://acme.test/pricing',
    ]);
});

it('never leaves the seed host', function () {
    Http::fake([
        '*/robots.txt' => Http::response('', 404),
        'https://acme.test/' => Http::response(html('<a href="https://elsewhere.test/about">Partner</a>')),
        '*' => Http::response(html('<p>Should not be fetched</p>')),
    ]);

    $pages = app(SiteCrawler::class)->crawl('https://acme.test');

    expect($pages)->toHaveCount(1);
});

it('obeys robots.txt', function () {
    Http::fake([
        '*/robots.txt' => Http::response("User-agent: *\nDisallow: /pricing\n"),
        'https://acme.test/' => Http::response(html('<a href="/pricing">Pricing</a><a href="/about">About</a>')),
        'https://acme.test/about' => Http::response(html('<p>About us</p>')),
        'https://acme.test/pricing' => Http::response(html('<p>Secret prices</p>')),
    ]);

    $pages = app(SiteCrawler::class)->crawl('https://acme.test');

    expect($pages->pluck('url')->all())->not->toContain('https://acme.test/pricing');
});

it('caches pages so a second crawl costs no requests', function () {
    Http::fake([
        '*/robots.txt' => Http::response('', 404),
        'https://acme.test/' => Http::response(html('<p>Only page</p>')),
    ]);

    app(SiteCrawler::class)->crawl('https://acme.test');
    $afterFirst = count(Http::recorded());

    app(SiteCrawler::class)->crawl('https://acme.test');

    expect(count(Http::recorded()))->toBe($afterFirst)
        ->and(CrawledPage::count())->toBe(1);
});

it('refetches once the cached copy has expired', function () {
    Http::fake([
        '*/robots.txt' => Http::response('', 404),
        'https://acme.test/' => Http::response(html('<p>Fresh</p>')),
    ]);

    app(SiteCrawler::class)->crawl('https://acme.test');
    CrawledPage::query()->update(['expires_at' => now()->subDay()]);
    $before = count(Http::recorded());

    app(SiteCrawler::class)->crawl('https://acme.test');

    expect(count(Http::recorded()))->toBeGreaterThan($before)
        ->and(CrawledPage::count())->toBe(1);
});

it('returns nothing when the site cannot be read', function () {
    Http::fake([
        '*/robots.txt' => Http::response('', 404),
        '*' => Http::response('', 500),
    ]);

    expect(app(SiteCrawler::class)->crawl('https://acme.test'))->toBeEmpty();
});

it('skips responses that are not html', function () {
    Http::fake([
        '*/robots.txt' => Http::response('', 404),
        'https://acme.test/' => Http::response('{"hello":"world"}', 200, ['Content-Type' => 'application/json']),
    ]);

    expect(app(SiteCrawler::class)->crawl('https://acme.test'))->toBeEmpty();
});

it('strips machinery, keeps the readable markdown, and keeps nav', function () {
    Http::fake([
        '*/robots.txt' => Http::response('', 404),
        'https://acme.test/' => Http::response(html(
            '<nav>Menu <a href="/contact">Contact</a></nav><p>Acme sells widgets.</p><script>var x = 1;</script>'
        )),
    ]);

    $page = app(SiteCrawler::class)->crawl('https://acme.test')->first();

    expect($page->text)->toContain('Acme sells widgets.')
        ->and($page->text)->not->toContain('var x = 1')
        // `nav` used to be stripped as chrome. Reversed on 2026-08-12 (ADR-033):
        // pagination lives there, and dropping it stops a listing harvest at
        // page one. The extra tokens are a rounding error against that.
        ->and($page->text)->toContain('[Contact](https://acme.test/contact)')
        ->and($page->language)->toBe('fr');
});

it('ranks account pages below everything else', function () {
    // Found on the first real run: restogo.be spent two of eleven page slots
    // on /login and /register, both of which render nothing without a session.
    Http::fake([
        '*/robots.txt' => Http::response('', 404),
        'https://acme.test/' => Http::response(html(
            '<a href="/login">Login</a><a href="/register">Register</a><a href="/panier">Panier</a>'
            .'<a href="/team">Team</a>'
        )),
        'https://acme.test/team' => Http::response(html('<p>Our team</p>')),
        'https://acme.test/login' => Http::response(html('')),
        'https://acme.test/register' => Http::response(html('')),
        'https://acme.test/panier' => Http::response(html('')),
    ]);

    $pages = app(SiteCrawler::class)->crawl('https://acme.test', maxPages: 2);

    expect($pages->pluck('url')->all())->toBe([
        'https://acme.test/',
        'https://acme.test/team',
    ]);
});
