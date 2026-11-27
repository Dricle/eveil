<?php

namespace App\Actions;

use App\Enums\LaunchVenueKind;
use App\Enums\LaunchVenueOrigin;
use App\Models\LaunchItem;
use App\Models\LaunchVenue;
use App\Models\Project;

/**
 * Brings a project's launch checklist in line with what the product is: every
 * catalog venue its tags suit, plus the subreddits its target profiles found.
 * No model: the catalog is tagged, the project's tags come from the website
 * analysis or the user (`Project::launchTags()`), so it runs on every read:
 * one insert-select and one delete, writing only what changed.
 *
 * A venue the tags no longer suit leaves the list only while untouched: one
 * that was posted to, given copy or dismissed keeps its row and history.
 */
class RefreshLaunchList
{
    public function handle(Project $project): void
    {
        $this->addSubreddits($project);

        $tags = json_encode($project->launchTags());

        LaunchItem::query()->insertOrIgnoreUsing(
            ['project_id', 'launch_venue_id', 'created_at', 'updated_at'],
            LaunchVenue::query()
                ->selectRaw('?, id, now(), now()', [$project->id])
                ->where(fn ($query) => $query
                    ->where('project_id', $project->id)
                    ->orWhere(fn ($query) => $query->whereNull('project_id')->whereRaw('tags <@ ?::jsonb', [$tags]))),
        );

        LaunchItem::query()
            ->where('project_id', $project->id)
            ->whereNull('dismissed_at')
            ->whereNull('draft')
            ->whereDoesntHave('submissions')
            ->whereHas('venue', fn ($query) => $query->whereNull('project_id')->whereRaw('not (tags <@ ?::jsonb)', [$tags]))
            ->delete();
    }

    /**
     * Every subreddit the target profiles found, as a venue of this project.
     * One the catalog already lists is skipped, so it is not on the
     * checklist twice.
     */
    private function addSubreddits(Project $project): void
    {
        $catalogKeys = LaunchVenue::query()->whereNull('project_id')->where('key', 'like', 'reddit.com/r/%')->pluck('key')->all();
        $now = now();

        $rows = $project->targetProfiles()->get()
            ->flatMap(fn ($profile): array => $profile->criteria['subreddits'] ?? [])
            ->map(fn (array $subreddit): string => EditTargetProfileSubreddits::name($subreddit['name']))
            ->unique(fn (string $name): string => mb_strtolower($name))
            ->reject(fn (string $name): bool => in_array('reddit.com/r/'.mb_strtolower($name), $catalogKeys, true))
            ->map(fn (string $name): array => [
                'project_id' => $project->id,
                'key' => 'reddit.com/r/'.mb_strtolower($name),
                'origin' => LaunchVenueOrigin::Ai->value,
                'name' => "r/{$name}",
                'url' => "https://www.reddit.com/r/{$name}/",
                'kind' => LaunchVenueKind::Community->value,
                'tags' => '[]',
                'rules' => LaunchVenue::SUBREDDIT_RULES,
                'cooldown_days' => LaunchVenue::SUBREDDIT_COOLDOWN_DAYS,
                'created_at' => $now,
                'updated_at' => $now,
            ])
            ->values()
            ->all();

        if ($rows !== []) {
            LaunchVenue::query()->upsert($rows, uniqueBy: ['project_id', 'key'], update: ['name', 'url', 'updated_at']);
        }
    }
}
