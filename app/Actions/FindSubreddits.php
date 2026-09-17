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
        $guesses = $this->strings($targetProfile, 'subreddit_guesses');

        $targetProfile->update([
            'criteria' => [
                ...$targetProfile->criteria,
                'subreddits' => $topics === [] && $guesses === []
                    ? []
                    : $this->finder->find($topics, $guesses),
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
        $topics = $this->strings($targetProfile, 'subreddit_topics');

        if ($topics !== []) {
            return $topics;
        }

        return $this->strings($targetProfile, 'sectors');
    }

    /**
     * @return array<int, string>
     */
    private function strings(TargetProfile $targetProfile, string $key): array
    {
        $values = $targetProfile->criteria[$key] ?? [];

        return is_array($values) ? array_values(array_filter($values, is_string(...))) : [];
    }
}
