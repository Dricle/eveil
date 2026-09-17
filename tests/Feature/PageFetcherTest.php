<?php

use App\Services\Discovery\PageFetcher;
use App\Support\Settings;
use Illuminate\Support\Facades\Http;

function realPage(): string
{
    return '<!doctype html><html lang="en"><body><p>'.str_repeat('This is a real page with real content. ', 20).'</p></body></html>';
}

function shellPage(): string
{
    return '<!doctype html><html lang="en"><body><p>Enable JavaScript and cookies to continue</p></body></html>';
}

/**
 * Cloudflare's real managed-challenge markers, confirmed live against a
 * blocked production fetch.
 */
function challengePage(): string
{
    return '<!doctype html><html><head><title>Just a moment...</title>'
        .'<meta http-equiv="content-security-policy" content="script-src \'nonce-x\' https://challenges.cloudflare.com">'
        .'</head><body>Enable JavaScript and cookies to continue</body></html>';
}

beforeEach(function () {
    app(Settings::class)->set('crawl.delay_ms', 0);
    config()->set('eveil.sources.flaresolverr.url', 'http://flaresolverr:8191');
    config()->set('eveil.sources.flaresolverr.max_timeout_ms', 60_000);
});

it('never calls the renderer for a page that reads fine', function () {
    Http::fake([
        '*/robots.txt' => Http::response('', 404),
        'https://acme.test/' => Http::response(realPage()),
    ]);

    app(PageFetcher::class)->fetch('https://acme.test/');

    Http::assertNotSent(fn ($request) => str_contains($request->url(), 'flaresolverr'));
});

it('uses the rendered page when the plain fetch came back a shell', function () {
    Http::fake([
        '*/robots.txt' => Http::response('', 404),
        'https://acme.test/' => Http::response(shellPage()),
        'flaresolverr:8191/v1' => Http::response([
            'status' => 'ok',
            'solution' => ['response' => realPage()],
        ]),
    ]);

    $page = app(PageFetcher::class)->fetch('https://acme.test/');

    expect($page->content)->toBe(realPage());
});

it('falls back to the shell page when no renderer is configured', function () {
    config()->set('eveil.sources.flaresolverr.url', null);

    Http::fake([
        '*/robots.txt' => Http::response('', 404),
        'https://acme.test/' => Http::response(shellPage()),
    ]);

    $page = app(PageFetcher::class)->fetch('https://acme.test/');

    expect($page->content)->toBe(shellPage());
});

it('falls back to the shell page when the renderer itself fails', function () {
    Http::fake([
        '*/robots.txt' => Http::response('', 404),
        'https://acme.test/' => Http::response(shellPage()),
        'flaresolverr:8191/v1' => Http::response(['status' => 'error'], 500),
    ]);

    $page = app(PageFetcher::class)->fetch('https://acme.test/');

    expect($page->content)->toBe(shellPage());
});

it('escalates a blocked response carrying Cloudflare\'s own challenge markers', function () {
    Http::fake([
        '*/robots.txt' => Http::response('', 404),
        'https://acme.test/' => Http::response(challengePage(), 403),
        'flaresolverr:8191/v1' => Http::response([
            'status' => 'ok',
            'solution' => ['response' => realPage()],
        ]),
    ]);

    $page = app(PageFetcher::class)->fetch('https://acme.test/');

    expect($page)->not->toBeNull()
        ->and($page->content)->toBe(realPage())
        ->and($page->status_code)->toBe(200);
});

it('never calls the renderer for an ordinary 403 with no challenge markers', function () {
    Http::fake([
        '*/robots.txt' => Http::response('', 404),
        'https://acme.test/' => Http::response('Forbidden', 403),
    ]);

    $page = app(PageFetcher::class)->fetch('https://acme.test/', $reason);

    expect($page)->toBeNull()
        ->and($reason)->toBe('The server answered 403.');

    Http::assertNotSent(fn ($request) => str_contains($request->url(), 'flaresolverr'));
});
