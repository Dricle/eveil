<?php

namespace App\Enums;

/**
 * What a host on the public web is, from a prospecting point of view.
 *
 * Decided once per host and remembered instance-wide, because the answer is a
 * fact about the open web rather than about any customer.
 */
enum HostKind: string
{
    /** A list of businesses — harvest it, do not treat it as one company. */
    case Index = 'index';

    /** A company's own site. The thing we are actually looking for. */
    case Entity = 'entity';

    /** A social platform. Never a prospect, and not harvestable. */
    case Social = 'social';

    /**
     * Structurally neither: search engines, encyclopaedias, forums, publishing
     * platforms, documentation.
     *
     * Called `other` and not `noise` deliberately. It says what the host is
     * NOT, never that it is worthless — a forum thread naming the best plumbers
     * in a city, or an article listing five companies that just raised money,
     * are real leads sitting on a host that is not itself a directory. We skip
     * them today because we read hosts, not pages; that is a limit of the
     * current implementation, not a judgement.
     */
    case Other = 'other';

    public function isProspect(): bool
    {
        return $this === self::Entity;
    }

    public function isHarvestable(): bool
    {
        return $this === self::Index;
    }
}
