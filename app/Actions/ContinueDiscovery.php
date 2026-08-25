<?php

namespace App\Actions;

use App\Models\Project;
use App\Models\TargetProfile;

/**
 * Starts the next discovery run for every active target profile that is ready
 * for one. Without this a run only ever happens when somebody types the
 * command: it hits its budget cap, gets marked `succeeded`, and nothing looks
 * again. See `RunDiscovery`, which this only decides WHEN to call.
 */
class ContinueDiscovery
{
    public function __construct(private RunDiscovery $discover) {}

    public function handle(Project $project): int
    {
        $started = 0;

        foreach ($project->targetProfiles()->where('is_active', true)->get() as $profile) {
            if ($this->ready($profile)) {
                $this->discover->handle($profile);
                $started++;
            }
        }

        return $started;
    }

    /**
     * Never two runs for the same profile at once, and never another run for a
     * profile the last one diagnosed as the wrong target: that is escalated to
     * the user, not re-searched.
     */
    private function ready(TargetProfile $profile): bool
    {
        if ($profile->discoveryRuns()->open()->exists()) {
            return false;
        }

        $latest = $profile->discoveryRuns()->latest('id')->first();

        return $latest === null || $latest->mayWiden();
    }
}
