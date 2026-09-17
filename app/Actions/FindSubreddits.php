<?php

namespace App\Actions;

use App\Models\TargetProfile;
use App\Services\Discovery\SubredditFinder;

/**
 * Resolves a target profile's Reddit topics into real, verified subreddits,
 * once - `DiscoveryPlanner` reads the result rather than guessing a name
 * fresh on every run. One entry point for both paths that create a profile:
 * `DeriveTargetProfiles` calls it right after an agent-derived one is
 * created, and it is just as callable on an existing, human-authored profile
 * (`TargetProfile::criteria` never ran through `TargetProfileDeriver` at all)
 * via `eveil:find-subreddits`.
 */
class FindSubreddits
{
    public function __construct(private SubredditFinder $finder) {}

    public function handle(TargetProfile $targetProfile): TargetProfile
    {
        $topics = $this->topics($targetProfile);

        $targetProfile->update([
            'criteria' => [
                ...$targetProfile->criteria,
                'subreddits' => $topics === [] ? [] : $this->finder->find($topics),
            ],
        ]);

        return $targetProfile->fresh();
    }

    /**
     * The agent's own proposal when there is one (an agent-derived profile);
     * otherwise its sectors are decent raw material even with nothing
     * AI-proposed - a human-authored profile like a manually built launch-signal
     * one never ran through `TargetProfileDeriver` at all.
     *
     * @return array<int, string>
     */
    private function topics(TargetProfile $targetProfile): array
    {
        $topics = $targetProfile->criteria['subreddit_topics'] ?? null;

        if (is_array($topics) && $topics !== []) {
            return array_values(array_filter($topics, is_string(...)));
        }

        $sectors = $targetProfile->criteria['sectors'] ?? [];

        return is_array($sectors) ? array_values(array_filter($sectors, is_string(...))) : [];
    }
}
