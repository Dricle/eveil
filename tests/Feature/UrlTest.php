<?php

use App\Support\Url;

/**
 * The crawl cache and the company dedupe both key on these, so a change in
 * shape means duplicate rows rather than a visible failure. Worth pinning
 * directly rather than only through whatever happens to call them.
 */
it('resolves every shape of relative reference', function (string $href, ?string $expected) {
    expect(Url::resolve($href, 'https://example.be/annuaire/friteries'))->toBe($expected);
})->with([
    // RFC 3986 §5.3: a query-only reference keeps the base PATH. Taking its
    // dirname instead sent every "page 2" link one directory up, so a listing
    // harvest read page one forever.
    'query only' => ['?page=2', 'https://example.be/annuaire/friteries?page=2'],
    'parent' => ['../contact', 'https://example.be/contact'],
    'current dir' => ['./equipe', 'https://example.be/annuaire/equipe'],
    'sibling' => ['equipe', 'https://example.be/annuaire/equipe'],
    'absolute path' => ['/about', 'https://example.be/about'],
    'protocol relative' => ['//cdn.test/x', 'https://cdn.test/x'],
    'already absolute' => ['https://other.test/y', 'https://other.test/y'],
    'double parent' => ['../../a/../b', 'https://example.be/b'],

    // Not pages we can fetch. Refused because the scheme is not http(s),
    // rather than by a hand-kept list.
    'mailto' => ['mailto:info@marcel.be', null],
    'tel' => ['tel:+3281223344', null],
    'javascript' => ['javascript:alert(1)', null],
    'data' => ['data:text/html,x', null],

    // A bare fragment is the page we are already on; resolving it would hand
    // back the same URL and the crawler would follow itself.
    'fragment' => ['#contact', null],
    'empty' => ['', null],
    'blank' => ['   ', null],
    'garbage' => ['not a url', null],
]);

it('normalises so one page is never two cache entries', function (string $url, ?string $expected) {
    expect(Url::normalize($url))->toBe($expected);
})->with([
    'host case' => ['HTTPS://Example.BE/about', 'https://example.be/about'],
    'trailing slash' => ['https://example.be/about/', 'https://example.be/about'],
    'root keeps its slash' => ['https://example.be', 'https://example.be/'],
    'fragment dropped' => ['https://example.be/about#team', 'https://example.be/about'],
    'query kept' => ['https://example.be/a?b=2', 'https://example.be/a?b=2'],
    'default port dropped' => ['https://example.be:443/a', 'https://example.be/a'],
    'real port kept' => ['https://example.be:8080/a', 'https://example.be:8080/a'],
    'ftp refused' => ['ftp://example.be/a', null],
    'hostless refused' => ['https:///a', null],
]);

it('reads the host without its display prefix, and the path', function () {
    expect(Url::host('https://WWW.Example.BE/about'))->toBe('example.be')
        ->and(Url::host('https://sub.example.be/'))->toBe('sub.example.be')
        ->and(Url::host('not a url'))->toBeNull()
        ->and(Url::path('https://example.be/annuaire/friteries?p=2'))->toBe('/annuaire/friteries')
        ->and(Url::path('https://example.be'))->toBe('/');
});
