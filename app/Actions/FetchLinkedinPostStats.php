<?php

namespace App\Actions;

use App\Enums\LinkedinPostExampleSource;
use App\Enums\LinkedinPostStatus;
use App\Models\LinkedinPost;
use App\Models\LinkedinPostExample;
use App\Services\Linkedin\LinkedinClient;
use App\Support\Settings;
use Illuminate\Support\Collection;
use Throwable;

/**
 * Reads real engagement on a published post and, past a threshold, promotes
 * it into the shared instance-wide bank - the only automatic way in, and
 * deliberately so: a real, externally-measured number is trusted the way a
 * self-reported click is not (see `.ai/rules` and `LinkedinPostController::promote()`).
 *
 * Runs across every project on the instance, same as `PromoteProvenEmails`:
 * there is no `CurrentProject` set from a console command, so
 * `LinkedinPost`'s `BelongsToProject` scope simply does not apply here.
 *
 * An account that never connected the separate Community Management app
 * (`LinkedinAccount::hasStatsAccess()`) is skipped outright, never attempted -
 * expected for most accounts, per LinkedIn's own "select developers only"
 * wording on `r_member_social_feed`.
 */
class FetchLinkedinPostStats
{
    public function __construct(private LinkedinClient $client, private Settings $settings) {}

    public function handle(): int
    {
        $minLikes = $this->settings->int('linkedin_examples.min_likes');
        $promoted = 0;

        foreach ($this->candidates() as $post) {
            $account = $post->linkedinAccount;

            if ($account === null || ! $account->hasStatsAccess()) {
                continue;
            }

            try {
                $likes = $this->client->socialMetadata($account, (string) $post->urn);
            } catch (Throwable) {
                continue;
            }

            $post->update(['likes_count' => $likes, 'stats_checked_at' => now()]);

            if ($likes < $minLikes) {
                continue;
            }

            LinkedinPostExample::create([
                'body' => $post->body,
                'source' => LinkedinPostExampleSource::Promoted,
                'linkedin_post_id' => $post->id,
            ]);

            $post->update(['promoted_at' => now()]);

            $promoted++;
        }

        return $promoted;
    }

    /**
     * Published, recent (a post's engagement settles quickly, so nothing
     * older than 30 days is worth the call), not already promoted.
     *
     * @return Collection<int, LinkedinPost>
     */
    private function candidates(): Collection
    {
        return LinkedinPost::query()
            ->where('status', LinkedinPostStatus::Published)
            ->whereNotNull('urn')
            ->whereNull('promoted_at')
            ->where('published_at', '>=', now()->subDays(30))
            ->with('linkedinAccount')
            ->get();
    }
}
