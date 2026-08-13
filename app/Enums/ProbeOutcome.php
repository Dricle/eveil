<?php

namespace App\Enums;

/**
 * What an SMTP probe actually established.
 *
 * The distinction that matters is the last two. A probe that returns "no
 * verdict" after a conversation tells us something about the SERVER; one that
 * never connected tells us something about OUR network — port 25 is blocked on
 * most hosting, and if that were read as "this provider refuses probes" the
 * first run on such a box would mark every mail provider on earth a refuser.
 */
enum ProbeOutcome: string
{
    case Accepted = 'accepted';
    case Rejected = 'rejected';

    /** Connected and talked, but the server would not say. Greylisting, 4xx, a shrug. */
    case NoVerdict = 'no_verdict';

    /** Never got a conversation. Says nothing about the server. */
    case Unreachable = 'unreachable';

    public function isVerdict(): bool
    {
        return $this === self::Accepted || $this === self::Rejected;
    }

    /** Only a completed conversation teaches anything about a provider. */
    public function isEvidence(): bool
    {
        return $this !== self::Unreachable;
    }
}
