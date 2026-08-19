<?php

namespace App\Enums;

enum MessageDirection: string
{
    case Outbound = 'outbound';
    case Inbound = 'inbound';

    public function isInbound(): bool
    {
        return $this === self::Inbound;
    }
}
