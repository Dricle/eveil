<?php

namespace App\Services;

use App\Models\CodeRepository;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * A repo's own files, for `RepoExplorer` to roam through. Project
 * self-knowledge, not a discovery source, which is why this lives beside
 * `SiteCrawler` rather than under `Discovery` - same reasoning
 * `HtmlText`/`ParsedPage` live in `Support`.
 *
 * GitHub only. Unauthenticated by default - reading a handful of files from
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
     * Owner, repo and default branch out of a URL - what `RepoExplorer`'s
     * tools need before they can fetch files one at a time.
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
     * Every path in the repo, unfiltered: the whole point of the explorer
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
