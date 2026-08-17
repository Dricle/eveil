<?php

namespace App\Enums;

enum DiscoveryTaskStatus: string
{
    case Pending = 'pending';
    case Running = 'running';
    case Succeeded = 'succeeded';

    /**
     * The node failed and the run carried on. One unreadable directory must
     * never cost the companies already found — a single malformed page is
     * enough to take a whole run down otherwise.
     */
    case Failed = 'failed';

    /**
     * Never ran: the budget was spent or the run was cancelled before a worker
     * reached this node. Kept rather than deleted, because "we stopped here" is
     * the most useful thing the screen can say about a run that came up short.
     */
    case Skipped = 'skipped';

    public function isOpen(): bool
    {
        return $this === self::Pending || $this === self::Running;
    }
}
