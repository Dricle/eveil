<?php

namespace App\Actions;

use App\Models\TargetProfile;
use App\Services\Discovery\SubredditFinder;
use Illuminate\Support\Collection;

/**
 * Adds or removes a target profile's subreddits by hand: from the profile
 * page, or Evie and MCP through `UpdateTargetProfile`. Both features read
 * the result, discovery probing it and the Reddit reply scan reading it.
 *
 * A name is checked against Reddit before it is kept, same as every name
 * `FindSubreddits` resolves. A re-check merges into the list rather than
 * replacing it, so a hand-added name stays.
 *
 * ponytail: a removed name can come back when a re-check finds it again on its
 * own. Add an exclusion list if that turns out to happen in practice.
 */
class EditTargetProfileSubreddits
{
    public function __construct(private SubredditFinder $finder) {}

    /**
     * @param  array<int, string>  $add
     * @param  array<int, string>  $remove
     * @return array<int, string> the names to add that Reddit does not know (or are too small / quarantined)
     */
    public function handle(TargetProfile $profile, array $add = [], array $remove = []): array
    {
        $refused = [];
        $subreddits = new Collection($profile->criteria['subreddits'] ?? []);

        foreach ($add as $name) {
            $subreddit = $this->finder->find([], [self::name($name)])[0] ?? null;

            if ($subreddit === null) {
                $refused[] = $name;
            } else {
                $subreddits->prepend($subreddit);
            }
        }

        $removed = array_map(fn (string $name): string => mb_strtolower(self::name($name)), $remove);

        $profile->update(['criteria' => [
            ...$profile->criteria,
            'subreddits' => $subreddits
                ->unique(fn (array $subreddit): string => mb_strtolower($subreddit['name']))
                ->reject(fn (array $subreddit): bool => in_array(mb_strtolower($subreddit['name']), $removed, true))
                ->values()
                ->all(),
        ]]);

        return $refused;
    }

    /**
     * `micro_saas`, `r/micro_saas` and a full reddit.com link all name the same community.
     */
    public static function name(string $name): string
    {
        return trim((string) preg_replace('#^(?:https?://)?(?:www\.|old\.)?(?:reddit\.com)?/?r/#i', '', trim($name)), '/ ');
    }
}
