<?php

use App\Ai\Tools\ListRepoPaths;
use App\Ai\Tools\ReadRepoFile;
use App\Services\RepoReader;
use Illuminate\Support\Facades\Http;
use Laravel\Ai\Tools\Request;

it('lists the repo root', function () {
    $tool = new ListRepoPaths(collect(['README.md', 'composer.json', 'src/index.js', 'src/lib/util.js']));

    $result = (string) $tool->handle(new Request(['directory' => '']));

    expect(explode("\n", $result))->toBe(['README.md', 'composer.json', 'src/']);
});

it('lists one directory deeper without repeating its own prefix', function () {
    $tool = new ListRepoPaths(collect(['README.md', 'src/index.js', 'src/lib/util.js']));

    $result = (string) $tool->handle(new Request(['directory' => 'src']));

    expect(explode("\n", $result))->toBe(['index.js', 'lib/']);
});

it('says plainly when a directory has nothing in it', function () {
    $tool = new ListRepoPaths(collect(['README.md']));

    $result = (string) $tool->handle(new Request(['directory' => 'nonexistent']));

    expect($result)->toContain('Nothing found');
});

it('reads one file by path through RepoReader', function () {
    Http::fake([
        'https://raw.githubusercontent.com/acme/widgets/main/README.md' => Http::response('# Widgets'),
    ]);

    $tool = new ReadRepoFile(app(RepoReader::class), 'acme', 'widgets', 'main');

    $result = (string) $tool->handle(new Request(['path' => 'README.md']));

    expect($result)->toBe('# Widgets');
});

it('explains rather than errors when a file cannot be read', function () {
    Http::fake([
        'https://raw.githubusercontent.com/acme/widgets/main/missing.md' => Http::response('', 404),
    ]);

    $tool = new ReadRepoFile(app(RepoReader::class), 'acme', 'widgets', 'main');

    $result = (string) $tool->handle(new Request(['path' => 'missing.md']));

    expect($result)->toContain('Could not read');
});
