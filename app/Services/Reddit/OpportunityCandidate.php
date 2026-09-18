<?php

namespace App\Services\Reddit;

use App\Enums\RedditReplySource;

/**
 * One thread `OpportunityScanner` or `SeoThreadFinder` found, not yet
 * triaged. A small object rather than an array shape: array shapes are not
 * covariant inside a Collection (see `App\Support\ParsedPage`'s docblock),
 * so passing them between the scanners, the job and the triage agent fights
 * static analysis for no benefit.
 */
readonly class OpportunityCandidate
{
    /**
     * @param  array<int, string>  $topComments  already fetched for a `seo_thread` candidate (needed to judge it at triage time); empty for `subreddit_scan`, fetched later only if accepted
     */
    public function __construct(
        public string $permalink,
        public ?string $subreddit,
        public RedditReplySource $source,
        public ?string $searchQuery,
        public string $author,
        public string $text,
        public ?string $threadTitle,
        public string $postId,
        public array $topComments = [],
    ) {}
}
