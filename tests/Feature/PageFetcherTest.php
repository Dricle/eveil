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
