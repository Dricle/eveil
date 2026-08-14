<?php

namespace App\Enums;

enum LeadStatus: string
{
    case New = 'new';
    case Queued = 'queued';
    case Contacted = 'contacted';
    case Replied = 'replied';
    case Suppressed = 'suppressed';
}
