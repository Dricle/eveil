<?php

namespace App\Enums;

/**
 * Where a Composio connection stands. Pending from the moment the user is
 * sent to authorize until they come back; error when the service refused it
 * or Composio lost it, which a reconnect fixes.
 */
enum ComposioConnectionStatus: string
{
    case Pending = 'pending';
    case Active = 'active';
    case Error = 'error';
}
