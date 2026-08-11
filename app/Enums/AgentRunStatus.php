<?php

namespace App\Enums;

enum AgentRunStatus: string
{
    case Pending = 'pending';
    case Running = 'running';
    case Succeeded = 'succeeded';
    case Failed = 'failed';
    case Aborted = 'aborted';
}
