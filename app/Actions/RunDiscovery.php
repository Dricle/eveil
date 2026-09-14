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
 * ponytail: a run that finds nothing at all gets one automatic do-over with a
 * different source, same run, same budget (`DiscoveryRun::pivotSource()`).
 * Loosening the TARGET itself - widening geography, size, sector one axis at
 * a time - is not: that needs the autonomy notches wired up, and a wrong
 * loosening reaches real inboxes in a way a wrong source never does. The
 * source pivot was worth having first because it is the safe half: it never
 * changes who gets contacted, only where the search looked.
 */
class RunDiscovery
{
    public function __construct(private Settings $settings) {}

    /**
     * @param  array{max_companies?: int, max_qualified?: int, max_pages?: int, max_queries?: int}  $overrides
     * @param  string|null  $guidance  A steer the user gave THIS run through Evie -
     *                                 "look at wholesalers instead", "focus on the
     *                                 northern half of the country". Handed to the
     *                                 planner verbatim, on top of the profile's own
     *                                 criteria, never folded into it.
     */
    public function handle(TargetProfile $targetProfile, array $overrides = [], ?string $guidance = null): DiscoveryRun
    {
        $run = DiscoveryRun::create([
            'project_id' => $targetProfile->project_id,
            'target_profile_id' => $targetProfile->id,
            'guidance' => $guidance,
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
