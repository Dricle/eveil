<?php

namespace App\Services;

use App\Models\CodeRepository;
use App\Support\Settings;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * A repo's own file list, into the same shape a page is: a path and its text.
 * Project self-knowledge, not a discovery source, which is why this lives
 * beside `SiteCrawler` rather than under `Discovery` — same reasoning
 * `HtmlText`/`ParsedPage` live in `Support`.
 *
 * GitHub only. Unauthenticated by default — reading a handful of files from
 * a public repo needs no token, and the unauthenticated rate limit (~60
 * requests/hour/IP) is a real ceiling on how often this can run, not a
 * correctness concern, the same tone as the SearXNG note about watching for
 * rate-limits. Every method takes an optional per-project token
 * (`Project::$github_token`) for the one thing an unauthenticated request
 * cannot do at all: GitHub answers 404 for a private repo either way, which
 * is indistinguishable from "does not exist" without one.
 */
class RepoReader
{
    /**
     * Matched against a path's basename, case-insensitively. Ecosystem
     * filenames are a closed world in the sense `.ai/rules/discovery.md`
     * already accepts for that: a fixed convention, not an open-world guess
     * the way a URL path keyword is, so no "learned" mechanism is needed.
     */
    private const EXACT_FILES = ['package.json', 'composer.json', 'cargo.toml', 'pyproject.toml', 'go.mod', 'gemfile'];

    private const PREFIXES = ['readme', 'changelog'];

    public function __construct(private Settings $settings) {}

    /**
     * @return Collection<int, array{path: string, text: string}>|null null
     *                                                                 when the URL is not a GitHub repo, or GitHub could not be reached
     */
    public function read(string $url, ?string &$reason = null, ?string $token = null): ?Collection
    {
        $reason = null;
        $parsed = CodeRepository::parseGithubUrl($url);

        if ($parsed === null) {
            $reason = 'Not a GitHub repository URL.';

            return null;
        }

        [$owner, $repo] = $parsed;

        $meta = $this->fetchJson("https://api.github.com/repos/{$owner}/{$repo}", $token);

        if ($meta === null) {
            $reason = "Could not read {$owner}/{$repo} from GitHub.";

            return null;
        }

        $branch = is_string($meta['default_branch'] ?? null) ? $meta['default_branch'] : 'main';
        $tree = $this->fetchJson("https://api.github.com/repos/{$owner}/{$repo}/git/trees/{$branch}?recursive=1", $token);

        $paths = collect(is_array($tree['tree'] ?? null) ? $tree['tree'] : [])
            ->map(fn (mixed $entry): mixed => is_array($entry) ? ($entry['path'] ?? null) : null)
            ->filter(fn (mixed $path): bool => is_string($path));

        $files = $this->selectPaths($paths, $this->settings->int('repo.max_files'))
            ->map(fn (string $path): ?array => $this->fetchRaw($owner, $repo, $branch, $path, $token))
            ->filter()
            ->values();

        return collect([['path' => '(repository)', 'text' => $this->metaText($meta)]])->merge($files);
    }

    /**
     * Owner, repo and default branch out of a URL — the half of `read()`
     * that `RepoExplorer`'s tools also need, since they fetch files one at a
     * time instead of a fixed priority list chosen upfront.
     *
     * @return array{owner: string, repo: string, branch: string}|null
     */
    public function resolve(string $url, ?string $token = null): ?array
    {
        $parsed = CodeRepository::parseGithubUrl($url);

        if ($parsed === null) {
            return null;
        }

        [$owner, $repo] = $parsed;

        $meta = $this->fetchJson("https://api.github.com/repos/{$owner}/{$repo}", $token);

        if ($meta === null) {
            return null;
        }

        $branch = is_string($meta['default_branch'] ?? null) ? $meta['default_branch'] : 'main';

        return ['owner' => $owner, 'repo' => $repo, 'branch' => $branch];
    }

    /**
     * Every path in the repo, unfiltered: `read()`'s own priority-file
     * allowlist has no place here, since the whole point of the explorer
     * agent is to decide for itself what is worth opening.
     *
     * @return Collection<int, string>
     */
    public function paths(string $owner, string $repo, string $branch, ?string $token = null): Collection
    {
        $tree = $this->fetchJson("https://api.github.com/repos/{$owner}/{$repo}/git/trees/{$branch}?recursive=1", $token);

        return collect(is_array($tree['tree'] ?? null) ? $tree['tree'] : [])
            ->map(fn (mixed $entry): mixed => is_array($entry) ? ($entry['path'] ?? null) : null)
            ->filter(fn (mixed $path): bool => is_string($path))
            ->values();
    }

    /**
     * One file's text, fetched on demand for the explorer's read tool.
     * Null covers missing, binary-rejected and over-budget alike: the tool
     * only needs to tell the model "could not read this", not why.
     */
    public function file(string $owner, string $repo, string $branch, string $path, ?string $token = null): ?string
    {
        return $this->fetchRaw($owner, $repo, $branch, $path, $token)['text'] ?? null;
    }

    /**
     * @param  Collection<int, string>  $paths
     * @return Collection<int, string>
     */
    private function selectPaths(Collection $paths, int $limit): Collection
    {
        return $paths
            ->filter(function (string $path): bool {
                $basename = mb_strtolower(basename($path));

                return in_array($basename, self::EXACT_FILES, true)
                    || collect(self::PREFIXES)->contains(fn (string $prefix): bool => str_starts_with($basename, $prefix));
            })
            // Root-level matches first: a monorepo's fiftieth nested
            // package.json is not the product's own tech stack.
            ->sortBy(fn (string $path): int => substr_count($path, '/'))
            ->take($limit)
            ->values();
    }

    /**
     * @param  array<string, mixed>  $meta
     */
    private function metaText(array $meta): string
    {
        $lines = [
            'Description: '.($meta['description'] ?? 'none given'),
            'Primary language: '.($meta['language'] ?? 'not detected'),
            'Topics: '.(is_array($meta['topics'] ?? null) && $meta['topics'] !== [] ? implode(', ', $meta['topics']) : 'none'),
            'Stars: '.($meta['stargazers_count'] ?? 0),
        ];

        return implode("\n", $lines);
    }

    /**
     * @return array<string, mixed>|null
     */
    private function fetchJson(string $url, ?string $token = null): ?array
    {
        try {
            $response = Http::withHeaders([
                'User-Agent' => (string) config('eveil.crawl.user_agent'),
                'Accept' => 'application/vnd.github+json',
                ...$this->authHeader($token),
            ])->timeout((int) config('eveil.crawl.timeout'))->get($url);
        } catch (Throwable) {
            return null;
        }

        if (! $response->successful()) {
            return null;
        }

        $json = $response->json();

        return is_array($json) ? $json : null;
    }

    /**
     * Public repos go straight to the CDN, same as always. A token switches
     * the request to the Contents API instead: raw.githubusercontent.com
     * does not honour an Authorization header for a private repo, so that
     * path stays 404 there no matter what is sent, while the API's own
     * "give me the file body, not JSON" media type does the same job once
     * authenticated.
     *
     * @return array{path: string, text: string}|null
     */
    private function fetchRaw(string $owner, string $repo, string $branch, string $path, ?string $token = null): ?array
    {
        $url = $token === null
            ? "https://raw.githubusercontent.com/{$owner}/{$repo}/{$branch}/{$path}"
            : "https://api.github.com/repos/{$owner}/{$repo}/contents/{$path}?ref={$branch}";

        try {
            $response = Http::withHeaders([
                'User-Agent' => (string) config('eveil.crawl.user_agent'),
                ...($token === null ? [] : ['Accept' => 'application/vnd.github.raw']),
                ...$this->authHeader($token),
            ])->timeout((int) config('eveil.crawl.timeout'))->get($url);
        } catch (Throwable) {
            return null;
        }

        if (! $response->successful()) {
            return null;
        }

        $body = $response->body();

        if (mb_strlen($body, '8bit') > (int) config('eveil.crawl.max_bytes')) {
            return null;
        }

        return ['path' => $path, 'text' => $this->storable($body)];
    }

    /**
     * @return array<string, string>
     */
    private function authHeader(?string $token): array
    {
        return $token === null ? [] : ['Authorization' => "Bearer {$token}"];
    }

    /**
     * Same reasoning as `PageFetcher::storable()`: Postgres text columns
     * reject NUL bytes and invalid UTF-8 outright, and a raw file can carry
     * both.
     */
    private function storable(string $body): string
    {
        $clean = str_replace("\0", '', $body);

        return mb_check_encoding($clean, 'UTF-8')
            ? $clean
            : (string) mb_convert_encoding($clean, 'UTF-8', 'UTF-8');
    }
}
