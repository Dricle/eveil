<?php

namespace App\Enums;

/**
 * Whether anyone has gone looking for people at this company yet. Null means
 * never asked; the states below are what the list badge says.
 */
enum ContactSearchStatus: string
{
    case Queued = 'queued';
    case Done = 'done';

    /**
     * The site could not be read, or the extraction blew up. Worth showing
     * rather than hiding: "nobody found" and "we could not look" are different
     * findings about a company, and only one of them is worth retrying.
     */
    case Failed = 'failed';

    public function isPending(): bool
    {
        return $this === self::Queued;
    }
}
