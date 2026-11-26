<?php

namespace App\Actions;

use App\Ai\Agents\LaunchPlanner;
use App\Enums\LaunchVenueKind;
use App\Enums\LaunchVenueOrigin;
use App\Models\AgentRun;
use App\Models\LaunchItem;
use App\Models\LaunchVenue;
use App\Models\Project;

/**
 * "Prepare my launch": fills a project's launch checklist with every venue
 * that suits it. The planner says what the product is in a few tags, the
 * catalog is filtered on them, and the subreddits the project's target
 * profiles found join as venues of their own.
 *
 * Safe to run again: it only adds. A venue already on the checklist keeps
 * its state, its draft and its posts, and one the product no longer suits is
 * left for the user to dismiss rather than vanishing with its history.
 */
class PrepareLaunch
{
    public function __construct(private SyncLaunchCatalog $sync) {}

    public function handle(Project $project, ?AgentRun $run = null): void
    {
        $this->sync->handle();

        $agent = new LaunchPlanner($project);

        if ($run !== null) {
            $agent->recordInto($run);
        }

        $tags = array_values(array_intersect($agent->plan()->structured['tags'] ?? [], array_keys(LaunchVenue::TAGS)));
        $project->update(['launch_tags' => $tags]);

        $this->addSubreddits($project);

        $now = now();

        LaunchItem::query()->insertOrIgnore(LaunchVenue::query()
            ->where(fn ($query) => $query->whereNull('project_id')->orWhere('project_id', $project->id))
            ->whereRaw('tags <@ ?::jsonb', [json_encode($tags)])
            ->pluck('id')
            ->map(fn (int $venueId): array => [
                'project_id' => $project->id,
                'launch_venue_id' => $venueId,
                'created_at' => $now,
                'updated_at' => $now,
            ])
            ->all());
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
