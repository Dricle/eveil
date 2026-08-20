<?php

namespace App\Enums;

use App\Jobs\Discovery\DiscoveryJob;
use App\Jobs\Discovery\HarvestListing;
use App\Jobs\Discovery\PlanDiscovery;
use App\Jobs\Discovery\QualifyCandidate;
use App\Jobs\Discovery\RunProbe;

/**
 * The nodes a discovery run is made of. Most of them never touch a model: the
 * agent decides WHERE to look, PHP does the volume.
 */
enum DiscoveryTaskKind: string
{
    /** One model call: where to look, and why, said before anything executes. */
    case Plan = 'plan';

    /** One probe put to one source: a map query or a web search. No model. */
    case Probe = 'probe';

    /** One directory page read for the businesses on it. Model only as a last resort. */
    case Harvest = 'harvest';

    /** One candidate's own site fetched and scored against the profile. One model call. */
    case Qualify = 'qualify';

    /**
     * What runs this node: which is also what a replay dispatches, since the
     * row already carries everything the job needs to start again.
     *
     * @return class-string<DiscoveryJob>
     */
    public function job(): string
    {
        return match ($this) {
            self::Plan => PlanDiscovery::class,
            self::Probe => RunProbe::class,
            self::Harvest => HarvestListing::class,
            self::Qualify => QualifyCandidate::class,
        };
    }
}
