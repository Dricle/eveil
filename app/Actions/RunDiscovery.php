<?php

namespace App\Actions;

use App\Enums\DiscoveryRunStatus;
use App\Enums\DiscoveryTaskKind;
use App\Enums\DiscoveryTaskStatus;
use App\Jobs\Discovery\PlanDiscovery;
use App\Models\DiscoveryRun;
use App\Models\DiscoveryTask;
use App\Models\TargetProfile;
use App\Support\Settings;

/**
 * Starts a search for companies matching one target profile, and comes back
 * immediately: the work is a graph of queued nodes, not a call that blocks for
 * several minutes.
 *
 * ponytail: a run that comes up short is diagnosed and reported, not widened.
 * Automatic widening needs the autonomy notches wired up and a re-plan cycle;
 * the diagnosis is the half that makes the other half safe, and it is worth
 * having first.
 */
class RunDiscovery
{
    public function __construct(private Settings $settings) {}

    /**
     * @param  array{max_companies?: int, max_qualified?: int, max_pages?: int, max_queries?: int}  $overrides
     */
    public function handle(TargetProfile $targetProfile, array $overrides = []): DiscoveryRun
    {
        $run = DiscoveryRun::create([
            'project_id' => $targetProfile->project_id,
            'target_profile_id' => $targetProfile->id,
            'status' => DiscoveryRunStatus::Planning,
            'budget' => [...$this->settings->array('discovery'), ...$overrides],
            'started_at' => now(),
        ]);

        PlanDiscovery::dispatch(DiscoveryTask::create([
            'project_id' => $run->project_id,
            'discovery_run_id' => $run->id,
            'kind' => DiscoveryTaskKind::Plan,
            'status' => DiscoveryTaskStatus::Pending,
        ]));

        return $run->refresh();
    }
}
