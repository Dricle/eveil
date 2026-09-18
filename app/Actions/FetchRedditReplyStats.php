<?php

namespace App\Actions;

use App\Enums\RedditReplyExampleSource;
use App\Enums\RedditReplyStatus;
use App\Models\RedditReply;
use App\Models\RedditReplyExample;
use App\Services\Discovery\FlareSolverrRenderer;
use App\Support\Settings;
use Illuminate\Support\Collection;

/**
 * Reads a real score on a self-reported-posted reply and, past a threshold,
 * promotes it into the shared instance-wide bank - the only automatic way
 * in, deliberately: a real, externally-measured number is trusted the way a
 * self-reported click is not (see `.ai/rules` and `RedditReplyController::promote()`).
 *
 * Runs across every project on the instance, same as `FetchLinkedinPostStats`:
 * there is no `CurrentProject` set from a console command, so `RedditReply`'s
 * `BelongsToProject` scope simply does not apply here.
 *
 * Two things ruled out first, both checked live, not assumed: a plain
 * unauthenticated `Http::get("{$permalink}.json")` gets a 403 from a server
 * (Cloudflare blocking the datacenter IP), and Arctic Shift's own mirrored
 * score on a comment is frozen a few seconds after it was posted - it never
 * gets re-crawled, so it cannot say how a comment did days later either.
 * `FlareSolverrRenderer` (already running in this stack for the same class
 * of block elsewhere) is what actually reaches the live score: it drives a
 * real headless Chrome, which means the returned "page" is Chrome's own
 * JSON viewer HTML wrapping the real payload in one `<pre>`, not raw JSON -
 * hence the `strip_tags()`/`html_entity_decode()` unwrap below.
 */
class FetchRedditReplyStats
{
    public function __construct(private FlareSolverrRenderer $renderer, private Settings $settings) {}

    public function handle(): int
    {
        $minScore = $this->settings->int('reddit_examples.min_score');
        $promoted = 0;

        foreach ($this->candidates() as $reply) {
            $score = $this->fetchScore((string) $reply->comment_permalink);

            if ($score === null) {
                continue;
            }

            $reply->update(['score' => $score, 'stats_checked_at' => now()]);

            if ($score < $minScore) {
                continue;
            }

            RedditReplyExample::create([
                'body' => $reply->body,
                'source' => RedditReplyExampleSource::Promoted,
                'reddit_reply_id' => $reply->id,
            ]);

            $reply->update(['promoted_at' => now()]);

            $promoted++;
        }

        return $promoted;
    }

    private function fetchScore(string $commentPermalink): ?int
    {
        $html = $this->renderer->render(rtrim($commentPermalink, '/').'.json');

        if ($html === null) {
            return null;
        }

        $decoded = json_decode(html_entity_decode(strip_tags($html)), true);

        if (! is_array($decoded) || ! isset($decoded[1]['data']['children'][0]['data'])) {
            return null;
        }

        $comment = $decoded[1]['data']['children'][0]['data'];

        return isset($comment['ups']) ? (int) $comment['ups'] : null;
    }

    /**
     * Published, self-reported with a comment link, recent (a comment's
     * score settles quickly, so nothing older than 30 days is worth the
     * call), not already promoted.
     *
     * @return Collection<int, RedditReply>
     */
    private function candidates(): Collection
    {
        return RedditReply::query()
            ->where('status', RedditReplyStatus::Published)
            ->whereNotNull('comment_permalink')
            ->whereNull('promoted_at')
            ->where('published_at', '>=', now()->subDays(30))
            ->get();
    }
}
