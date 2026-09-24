<?php

namespace App\Jobs;

use App\Services\Discovery\PageFetcher;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Reads a just-published article's page into the shared page cache, so the
 * site's own content includes it from now on. Queued rather than done in the
 * request: a fetch waits on robots.txt and the politeness delay.
 */
class FetchPublishedArticle implements ShouldQueue
{
    use Queueable;

    public function __construct(public string $url)
    {
        $this->onQueue('crawl');
    }

    public function handle(PageFetcher $fetcher): void
    {
        $fetcher->fetch($this->url);
    }
}
