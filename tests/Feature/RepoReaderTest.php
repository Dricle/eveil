<?php

use App\Services\RepoReader;
use App\Support\Settings;
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

it('reads the repo metadata and its priority files', function () {
    fakeGithub();

    $files = app(RepoReader::class)->read('https://github.com/acme/widgets');

    expect($files)->not->toBeNull()
        ->and($files->pluck('path')->all())->toBe(['(repository)', 'README.md', 'package.json'])
        ->and($files->firstWhere('path', '(repository)')['text'])->toContain('Widgets, but for the web.')
        ->and($files->firstWhere('path', 'README.md')['text'])->toBe('# Widgets');
});

it('never fetches a file outside the priority list', function () {
    fakeGithub();

    app(RepoReader::class)->read('https://github.com/acme/widgets');

    Http::assertNotSent(fn ($request) => str_contains($request->url(), 'src/index.js'));
});

it('respects the file budget', function () {
    app(Settings::class)->set('repo.max_files', 1);
    fakeGithub();

    $files = app(RepoReader::class)->read('https://github.com/acme/widgets');

    // The synthetic metadata entry plus exactly one fetched file.
    expect($files)->toHaveCount(2);
});

it('refuses anything that is not a github.com URL', function () {
    $reason = null;
    $files = app(RepoReader::class)->read('https://gitlab.com/acme/widgets', $reason);

    expect($files)->toBeNull()
        ->and($reason)->toContain('GitHub');

    Http::assertNothingSent();
});

it('fails cleanly when GitHub answers 404', function () {
    Http::fake(['https://api.github.com/repos/acme/gone' => Http::response('', 404)]);

    $reason = null;
    $files = app(RepoReader::class)->read('https://github.com/acme/gone', $reason);

    expect($files)->toBeNull()
        ->and($reason)->not->toBeNull();
});

it('resolves a URL to owner, repo and default branch, for the explorer agent', function () {
    fakeGithub();

    expect(app(RepoReader::class)->resolve('https://github.com/acme/widgets'))
        ->toBe(['owner' => 'acme', 'repo' => 'widgets', 'branch' => 'main']);
});

it('resolves nothing for a non-GitHub URL', function () {
    expect(app(RepoReader::class)->resolve('https://gitlab.com/acme/widgets'))->toBeNull();
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
