<?php

namespace App\Services\Reddit;

/**
 * One comment on a thread, as Arctic Shift mirrors it. A small object rather
 * than the plain body string this used to be, for the same covariance reason
 * as `OpportunityCandidate`: `App\Actions\HarvestRedditReplyExamples` needs
 * the score the comment was ranked on and the link back to it, and both come
 * from the SAME response the tone sample already reads - never a second
 * fetch.
 *
 * `id` and `permalink` are empty when Arctic Shift's record has neither;
 * the harvest skips a comment it cannot identify rather than storing one it
 * could never dedupe against.
 */
readonly class TopComment
{
    public function __construct(
        public string $id,
        public string $body,
        public string $author,
        public ?string $subreddit,
        public string $permalink,
        public int $score,
    ) {}
}
