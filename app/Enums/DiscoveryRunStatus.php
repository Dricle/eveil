<?php

namespace App\Enums;

enum DiscoveryRunStatus: string
{
    case Pending = 'pending';
    case Planning = 'planning';
    case Running = 'running';
    case Succeeded = 'succeeded';

    /**
     * The market is finished, not the run: "your market is 40 companies, here
     * they are" is a result worth reporting, not a failure.
     */
    case Exhausted = 'exhausted';

    case Aborted = 'aborted';
    case Failed = 'failed';

    public function isTerminal(): bool
    {
        return in_array($this, [self::Succeeded, self::Exhausted, self::Aborted, self::Failed], strict: true);
    }
}
