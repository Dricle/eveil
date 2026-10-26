<?php

namespace App\Actions;

use App\Enums\DiscoveryDiagnosis;
use App\Enums\DiscoveryRunOrigin;
use App\Enums\DiscoveryRunStatus;
use App\Enums\TargetProfileSource;
use App\Models\DiscoveryRun;
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
    /** How often `eveil:discover-due` ticks (routes/console.php). */
    private const CADENCE_HOURS = 6;

    private const MAX_BACKOFF_HOURS = 24 * 7;

    /** Enough doublings to reach the cap: 6h × 2^5 > a week. */
    private const MAX_SATURATED_STREAK = 5;

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
     *
     * A manual run (a user pasting links they already had) never enters this
     * decision: it must not block the cadence while it runs, and it carries no
     * verdict on whether the profile is worth searching again.
     */
    private function ready(TargetProfile $profile): bool
    {
        $searches = $profile->discoveryRuns()->where('origin', DiscoveryRunOrigin::Search);

        if ((clone $searches)->open()->exists()) {
            return false;
        }

        if (! $this->isTrusted($profile)) {
            return false;
        }

        $latest = (clone $searches)->latest('id')->first();

        if ($latest === null) {
            return true;
        }

        return $latest->mayWiden() && ! $this->isBackingOff($profile);
    }

    /**
     * A saturated profile is proven, so it is never stopped, but every run on
     * it still pays for a plan and a round of qualifications that find
     * nothing. Measured on prod: saturated runs were 61% of paying
     * organizations' discovery credits, for zero companies, because the
     * cadence re-ran them every six hours. Each consecutive saturated run
     * doubles the wait, capped at a week; one productive run resets it.
     *
     * Failed runs (refused for credits, a crash) say nothing about the market,
     * so they neither count toward the streak nor break it.
     */
    private function isBackingOff(TargetProfile $profile): bool
    {
        $finished = $profile->discoveryRuns()
            ->where('origin', DiscoveryRunOrigin::Search)
            ->where('status', '!=', DiscoveryRunStatus::Failed)
            ->whereNotNull('finished_at')
            ->latest('id')
            ->limit(self::MAX_SATURATED_STREAK)
            ->get(['diagnosis', 'finished_at']);

        $streak = $finished->takeWhile(fn (DiscoveryRun $run): bool => $run->diagnosis === DiscoveryDiagnosis::Saturated)->count();

        if ($streak === 0) {
            return false;
        }

        $waitHours = min(self::CADENCE_HOURS * 2 ** $streak, self::MAX_BACKOFF_HOURS);

        return $finished->first()->finished_at->addHours($waitHours)->isFuture();
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
