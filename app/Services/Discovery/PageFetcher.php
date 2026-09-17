<?php

namespace App\Services\Discovery;

use App\Models\CrawledPage;
use App\Support\HtmlText;
use App\Support\Settings;
use App\Support\Url;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * One page in, one cached row out.
 *
 * The cache is shared instance-wide: it holds public web content and
 * nothing else, which is what makes sharing it across tenants safe. It pays off
 * as much on a re-run of one project as it does across projects.
 */
class PageFetcher
{
    /**
     * Below this many characters of real text, the server sent a shell
     * rather than a page - one of two signals `FlareSolverrRenderer` can fix.
     * Deliberately NOT shared with `Harvest::READABLE_TEXT`: the two serve
     * different layers (page-level "was this real" vs. listing-level "was
     * this a directory") and happen to agree on the number today, not by a
     * dependency between them.
     *
     * A length proxy alone is too weak on its own - it never fires on a
     * blocked (403/503) response at all, since that branch returns before
     * any text is parsed, and it can both false-positive on a genuinely thin
     * real page and false-negative on a wordy challenge page. Paired with
     * `CHALLENGE_MARKERS`, a positive text signature straight out of
     * Cloudflare's own challenge page.
     */
    private const SHELL_TEXT_LENGTH = 500;

    /**
     * Cloudflare's own markers for a JS challenge page, not an inference from
     * page length. Confirmed live against a real blocked fetch (managed
     * challenge, `cType: 'managed'`): the title, the domain the challenge
     * script loads from, and the response header/cookie name Cloudflare uses
     * once mitigation is active.
     */
    private const CHALLENGE_MARKERS = ['Just a moment', 'challenges.cloudflare.com', 'cf-mitigated'];

    /** @var array<string, int> host => last fetch, in milliseconds */
    private array $lastFetchedAt = [];

    public function __construct(private RobotsPolicy $robots, private Settings $settings) {}

    /**
     * @param  string|null  $reason  why nothing came back, in words a user can
     *                               act on. Only written when the fetch fails:
     *                               every caller that does not care simply
     *                               omits it.
     */
    public function fetch(string $url, ?string &$reason = null): ?CrawledPage
    {
        $reason = null;
        $url = Url::normalize($url);

        if ($url === null) {
            $reason = 'Not a usable address.';

            return null;
        }

        $cached = CrawledPage::where('url_hash', CrawledPage::hashFor($url))->first();

        if ($cached !== null && $cached->isFresh()) {
            return $cached;
        }

        if (! $this->robots->allows($url)) {
            $reason = 'Disallowed by robots.txt.';

            return null;
        }

        $this->throttle($url);

        try {
            $response = Http::withHeaders(['User-Agent' => (string) config('eveil.crawl.user_agent')])
                ->timeout((int) config('eveil.crawl.timeout'))
                ->withOptions(['allow_redirects' => ['max' => 3]])
                ->get($url);
        } catch (Throwable $e) {
            $reason = 'Unreachable: '.$e->getMessage();

            return null;
        }

        // A blocked response never reaches the text-length check below - it
        // returns before any HTML is even parsed - so a 403/503 carrying
        // Cloudflare's own challenge markers gets its own, earlier shot at
        // the renderer instead of failing outright.
        if (! $response->successful() && $this->hasChallengeMarkers($response->body())) {
            $rendered = app(FlareSolverrRenderer::class)->render($url);

            if ($rendered !== null) {
                return $this->store($url, 200, 'text/html; charset=utf-8', $rendered);
            }
        }

        $contentType = $response->header('Content-Type');

        if (! $response->successful()) {
            $reason = 'The server answered '.$response->status().'.';

            return null;
        }

        if (! $this->isHtml($contentType)) {
            $reason = 'Not a web page ('.($contentType === '' ? 'no content type' : $contentType).').';

            return null;
        }

        $body = $response->body();

        if (mb_strlen($body, '8bit') > (int) config('eveil.crawl.max_bytes')) {
            $reason = 'Too big to read safely.';

            return null;
        }

        $parsed = (new HtmlText)->parse($body, $url);

        // A managed challenge routinely answers 200 with the interstitial -
        // real HTTP success, nothing to read. Either signal is enough: a
        // page can be short without being a challenge (thin real site) and a
        // wordy challenge page would slip past a length check alone.
        if (mb_strlen(trim($parsed->text)) < self::SHELL_TEXT_LENGTH || $this->hasChallengeMarkers($body)) {
            $rendered = app(FlareSolverrRenderer::class)->render($url);

            if ($rendered !== null) {
                $body = $rendered;
                $parsed = (new HtmlText)->parse($body, $url);
            }
        }

        return $this->store($url, $response->status(), $contentType, $body, $parsed->language);
    }

    private function hasChallengeMarkers(string $body): bool
    {
        foreach (self::CHALLENGE_MARKERS as $marker) {
            if (str_contains($body, $marker)) {
                return true;
            }
        }

        return false;
    }

    private function store(string $url, int $statusCode, string $contentType, string $body, ?string $language = null): CrawledPage
    {
        $language ??= (new HtmlText)->parse($body, $url)->language;

        return CrawledPage::updateOrCreate(
            ['url_hash' => CrawledPage::hashFor($url)],
            [
                'url' => $url,
                'status_code' => $statusCode,
                'content_type' => $contentType,
                'language' => $language,
                'content' => $this->storable($body),
                'fetched_at' => now(),
                'expires_at' => now()->addDays($this->settings->int('crawl.cache_ttl_days')),
            ],
        );
    }

    /**
     * PostgreSQL text columns reject NUL bytes and invalid UTF-8 outright, and
     * real pages contain both. A mis-encoded Belgian restaurant site killed a
     * whole discovery run on the first live attempt.
     */
    private function storable(string $body): string
    {
        $clean = str_replace("\0", '', $body);

        return mb_check_encoding($clean, 'UTF-8')
            ? $clean
            : (string) mb_convert_encoding($clean, 'UTF-8', 'UTF-8');
    }

    /**
     * A missing Content-Type is accepted: plenty of small sites omit it, and
     * refusing them would silently skip pages that read perfectly well. Only an
     * explicit non-HTML type is rejected.
     */
    private function isHtml(string $contentType): bool
    {
        $contentType = mb_strtolower(trim($contentType));

        return $contentType === '' || str_contains($contentType, 'text/html');
    }

    /**
     * Per-host politeness delay. ponytail: an in-process sleep, so it only
     * paces one worker. Move it to a Redis-backed limiter once several workers
     * crawl the same host concurrently.
     */
    private function throttle(string $url): void
    {
        $host = Url::host($url) ?? '';
        $delay = $this->settings->int('crawl.delay_ms');
        $elapsed = (int) ((microtime(true) * 1000) - ($this->lastFetchedAt[$host] ?? 0));

        if (isset($this->lastFetchedAt[$host]) && $elapsed < $delay) {
            usleep(($delay - $elapsed) * 1000);
        }

        $this->lastFetchedAt[$host] = (int) (microtime(true) * 1000);
    }
}
