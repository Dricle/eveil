<?php

namespace App\Enums;

enum MessageDirection: string
{
    case Outbound = 'outbound';
    case Inbound = 'inbound';
}
