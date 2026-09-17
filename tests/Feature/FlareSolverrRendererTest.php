<?php

use App\Services\Discovery\FlareSolverrRenderer;
use App\Support\Settings;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    app(Settings::class)->set('crawl.delay_ms', 0);
});

it('never calls out when no renderer is configured', function () {
    config()->set('eveil.sources.flaresolverr.url', null);
    Http::fake();

    expect(app(FlareSolverrRenderer::class)->render('https://example.test/'))->toBeNull();

    Http::assertNothingSent();
});

it('returns the rendered page on a clean solve', function () {
    config()->set('eveil.sources.flaresolverr.url', 'http://flaresolverr:8191');
    config()->set('eveil.sources.flaresolverr.max_timeout_ms', 60_000);

    Http::fake([
        'flaresolverr:8191/v1' => Http::response([
            'status' => 'ok',
            'solution' => ['response' => '<html><body>Real page content.</body></html>'],
        ]),
    ]);

    expect(app(FlareSolverrRenderer::class)->render('https://example.test/'))
        ->toBe('<html><body>Real page content.</body></html>');
});

it('returns null when the renderer could not solve the challenge', function () {
    config()->set('eveil.sources.flaresolverr.url', 'http://flaresolverr:8191');

    Http::fake([
        'flaresolverr:8191/v1' => Http::response(['status' => 'error', 'message' => 'Challenge not solved']),
    ]);

    expect(app(FlareSolverrRenderer::class)->render('https://example.test/'))->toBeNull();
});

it('returns null instead of throwing when the renderer is unreachable', function () {
    config()->set('eveil.sources.flaresolverr.url', 'http://flaresolverr:8191');

    Http::fake(['flaresolverr:8191/v1' => fn () => throw new RuntimeException('connection refused')]);

    expect(app(FlareSolverrRenderer::class)->render('https://example.test/'))->toBeNull();
});
