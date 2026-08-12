<?php

namespace App\Discovery;

use App\Models\CrawledPage;
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
    /** @var array<string, int> host => last fetch, in milliseconds */
    private array $lastFetchedAt = [];

    public function __construct(private RobotsPolicy $robots) {}

    public function fetch(string $url): ?CrawledPage
    {
        $url = Url::normalize($url);

        if ($url === null) {
            return null;
        }

        $cached = CrawledPage::where('url_hash', CrawledPage::hashFor($url))->first();

        if ($cached !== null && $cached->isFresh()) {
            return $cached;
        }

        if (! $this->robots->allows($url)) {
            return null;
        }

        $this->throttle($url);

        try {
            $response = Http::withHeaders(['User-Agent' => (string) config('eveil.crawl.user_agent')])
                ->timeout((int) config('eveil.crawl.timeout'))
                ->withOptions(['allow_redirects' => ['max' => 3]])
                ->get($url);
        } catch (Throwable) {
            return null;
        }

        $contentType = $response->header('Content-Type');

        if (! $response->successful() || ! $this->isHtml($contentType)) {
            return null;
        }

        $body = $response->body();

        if (mb_strlen($body, '8bit') > (int) config('eveil.crawl.max_bytes')) {
            return null;
        }

        $parsed = (new HtmlText)->parse($body, $url);

        return CrawledPage::updateOrCreate(
            ['url_hash' => CrawledPage::hashFor($url)],
            [
                'url' => $url,
                'status_code' => $response->status(),
                'content_type' => $contentType,
                'language' => $parsed->language,
                'content' => $this->storable($body),
                'fetched_at' => now(),
                'expires_at' => now()->addDays((int) config('eveil.crawl.cache_ttl_days')),
            ],
        );
    }

    /**
     * PostgreSQL text columns reject NUL bytes and invalid UTF-8 outright, and
     * real pages contain both — a mis-encoded Belgian restaurant site killed a
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
        $delay = (int) config('eveil.crawl.delay_ms');
        $elapsed = (int) ((microtime(true) * 1000) - ($this->lastFetchedAt[$host] ?? 0));

        if (isset($this->lastFetchedAt[$host]) && $elapsed < $delay) {
            usleep(($delay - $elapsed) * 1000);
        }

        $this->lastFetchedAt[$host] = (int) (microtime(true) * 1000);
    }
}
