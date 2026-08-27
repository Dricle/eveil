<?php

use App\Services\RepoReader;
use Illuminate\Support\Facades\Http;

function fakeGithub(array $tree = ['README.md', 'package.json', 'src/index.js'], array $overrides = []): void
{
    Http::fake([
        'https://api.github.com/repos/acme/widgets' => Http::response([
            'description' => 'Widgets, but for the web.',
            'language' => 'TypeScript',
            'topics' => ['widgets', 'web'],
            'stargazers_count' => 42,
            'default_branch' => 'main',
        ]),
        'https://api.github.com/repos/acme/widgets/git/trees/main*' => Http::response([
            'tree' => array_map(fn (string $path): array => ['path' => $path, 'type' => 'blob'], $tree),
        ]),
        'https://raw.githubusercontent.com/acme/widgets/main/README.md' => Http::response('# Widgets'),
        'https://raw.githubusercontent.com/acme/widgets/main/package.json' => Http::response('{"name":"widgets"}'),
        ...$overrides,
    ]);
}

it('resolves a URL to owner, repo and default branch, for the explorer agent', function () {
    fakeGithub();

    expect(app(RepoReader::class)->resolve('https://github.com/acme/widgets'))
        ->toBe(['owner' => 'acme', 'repo' => 'widgets', 'branch' => 'main']);
});

it('resolves nothing for a non-GitHub URL', function () {
    expect(app(RepoReader::class)->resolve('https://gitlab.com/acme/widgets'))->toBeNull();
});

it('resolves nothing when GitHub answers 404', function () {
    Http::fake(['https://api.github.com/repos/acme/gone' => Http::response('', 404)]);

    expect(app(RepoReader::class)->resolve('https://github.com/acme/gone'))->toBeNull();
});

it('lists every path in the repo, unfiltered by the priority allowlist', function () {
    fakeGithub(['README.md', 'package.json', 'src/index.js']);

    expect(app(RepoReader::class)->paths('acme', 'widgets', 'main')->all())
        ->toBe(['README.md', 'package.json', 'src/index.js']);
});

it('reads one file on demand for the explorer agent', function () {
    fakeGithub();

    expect(app(RepoReader::class)->file('acme', 'widgets', 'main', 'README.md'))->toBe('# Widgets');
});

it('returns null for a file that cannot be read', function () {
    Http::fake(['https://raw.githubusercontent.com/acme/widgets/main/gone.md' => Http::response('', 404)]);

    expect(app(RepoReader::class)->file('acme', 'widgets', 'main', 'gone.md'))->toBeNull();
});

it('sends the token as a bearer header on every GitHub request, when given one', function () {
    fakeGithub([], [
        'https://api.github.com/repos/acme/widgets/contents/README.md*' => Http::response('# Widgets'),
    ]);

    app(RepoReader::class)->resolve('https://github.com/acme/widgets', 'ghp_secret');
    app(RepoReader::class)->file('acme', 'widgets', 'main', 'README.md', 'ghp_secret');

    Http::assertSent(fn ($request): bool => $request->url() === 'https://api.github.com/repos/acme/widgets'
        && $request->hasHeader('Authorization', 'Bearer ghp_secret'));

    Http::assertSent(fn ($request): bool => str_starts_with($request->url(), 'https://api.github.com/repos/acme/widgets/contents/README.md')
        && $request->hasHeader('Authorization', 'Bearer ghp_secret'));
});

it('reads a file through the Contents API instead of the CDN once a token is given', function () {
    Http::fake([
        'https://api.github.com/repos/acme/widgets/contents/README.md*' => Http::response('# Private widgets'),
    ]);

    $text = app(RepoReader::class)->file('acme', 'widgets', 'main', 'README.md', 'ghp_secret');

    expect($text)->toBe('# Private widgets');

    Http::assertNotSent(fn ($request): bool => str_contains($request->url(), 'raw.githubusercontent.com'));
});

it('never sends a token when none is given', function () {
    fakeGithub();

    app(RepoReader::class)->resolve('https://github.com/acme/widgets');

    Http::assertSent(fn ($request): bool => ! $request->hasHeader('Authorization'));
});
