<?php

namespace App\Enums;

/**
 * What a webhook can subscribe to. Two on purpose: the moments a CRM acts on.
 * A contact found or a mail sent is noise there, hundreds per run.
 *
 * - `ReplyReceived`: a reply was read and classified (interested, not now,
 *   unsubscribe...). Never an out-of-office: nobody wants one in their CRM.
 * - `StatusChanged`: a contact or company moved, whoever moved it: the reply
 *   agent, or the user marking a deal won.
 */
enum WebhookEvent: string
{
    case ReplyReceived = 'reply.received';
    case StatusChanged = 'status.changed';
}
