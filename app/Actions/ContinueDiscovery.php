<?php

namespace App\Actions;

use App\Enums\TargetProfileSource;
use App\Models\Project;
use App\Models\TargetProfile;
use App\Support\Settings;

/**
 * Starts the next discovery run for every active target profile that is ready
 * for one. Without this a run only ever happens when somebody types the
 * command: it hits its budget cap, gets marked `succeeded`, and nothing looks
 * again. See `RunDiscovery`, which this only decides WHEN to call.
 */
class ContinueDiscovery
{
    public function __construct(private RunDiscovery $discover, private Settings $settings) {}

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
     * the user, not re-searched. And never an automatic run for an
     * agent-authored profile the model itself was not confident about: this is
     * the one thing that also protects a future "propose a profile mid-run"
     * job, since whatever it sets `is_active` to, it still has to come through
     * here to actually spend budget.
     */
    private function ready(TargetProfile $profile): bool
    {
        if ($profile->discoveryRuns()->open()->exists()) {
            return false;
        }

        if (! $this->isTrusted($profile)) {
            return false;
        }

        $latest = $profile->discoveryRuns()->latest('id')->first();

        return $latest === null || $latest->mayWiden();
    }

    /**
     * A human-authored profile, or an agent one that reported no confidence at
     * all, is always trusted: only a REPORTED low score gates.
     */
    private function isTrusted(TargetProfile $profile): bool
    {
        if ($profile->source === TargetProfileSource::Human) {
            return true;
        }

        $confidence = $profile->criteria['confidence'] ?? null;

        return $confidence === null || $confidence >= $this->settings->array('discovery')['min_profile_confidence'];
    }
}
