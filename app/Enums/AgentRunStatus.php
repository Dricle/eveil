<?php

namespace App\Enums;

enum AgentRunStatus: string
{
    case Pending = 'pending';
    case Running = 'running';
    case Succeeded = 'succeeded';
    case Failed = 'failed';
    case Aborted = 'aborted';

    /**
     * Queued but not finished. `Pending` is the gap the queue owns: the job is
     * waiting for a worker and no provider call has started, and a screen
     * reporting work in progress has to count it, or a click looks like it did
     * nothing until a worker picks the job up.
     */
    public function isInFlight(): bool
    {
        return $this === self::Pending || $this === self::Running;
    }
}
