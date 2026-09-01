<?php

namespace App\Actions;

use App\Enums\DiscoveryRunOrigin;
use App\Enums\DiscoveryRunStatus;
use App\Enums\DiscoveryTaskKind;
use App\Enums\DiscoveryTaskStatus;
use App\Jobs\Discovery\ClassifyLink;
use App\Models\DiscoveryRun;
use App\Models\DiscoveryTask;
use App\Models\TargetProfile;
use App\Support\Settings;

/**
 * Starts a run for links a user already had, rather than ones an agent went
 * looking for. Sibling to `RunDiscovery`: same run/task graph, same budget
 * columns, same run screen, but there is nowhere to plan and nothing to
 * search, so it forks straight to one `Classify` node per URL.
 */
class SubmitDiscoveryLinks
{
    public function __construct(private Settings $settings) {}

    /**
     * @param  array<int, string>  $urls  already normalised, deduped
     */
    public function handle(TargetProfile $targetProfile, array $urls): DiscoveryRun
    {
        $budget = $this->settings->array('discovery');

        $run = DiscoveryRun::create([
            'project_id' => $targetProfile->project_id,
            'target_profile_id' => $targetProfile->id,
            'origin' => DiscoveryRunOrigin::Manual,
            // No planning stage: the links themselves are where to look.
            'status' => DiscoveryRunStatus::Running,
            'budget' => [
                ...$budget,
                // A ceiling sized for what an AI plan asks for must never
                // silently truncate a batch of links the user chose on purpose.
                'max_companies' => max($budget['max_companies'], count($urls)),
            ],
            'stats' => ['plan' => count($urls) === 1
                ? '1 link submitted by the user.'
                : count($urls).' links submitted by the user.'],
            'started_at' => now(),
        ]);

        // Every row is created before any job is dispatched. The queue is
        // synchronous in dev and in tests, so dispatching the first task would
        // otherwise run it to completion, ask `finishIfIdle()` whether
        // anything else is open, and find nothing yet: the rows for the other
        // URLs would not exist yet to say otherwise, and a 3-link submission
        // would close the run after reading just the first one.
        $tasks = collect($urls)->map(fn (string $url): DiscoveryTask => DiscoveryTask::create([
            'project_id' => $run->project_id,
            'discovery_run_id' => $run->id,
            'kind' => DiscoveryTaskKind::Classify,
            'status' => DiscoveryTaskStatus::Pending,
            'payload' => ['url' => $url],
        ]));

        $tasks->each(fn (DiscoveryTask $task) => ClassifyLink::dispatch($task));

        return $run->refresh();
    }
}
