<?php

namespace App\Services\Outreach;

/**
 * A delivery failure that arrived as a mail, hours after the send it is about.
 *
 * The distinction that matters is hard against soft. A hard bounce means the
 * address does not exist and must never be written to again — retrying one is
 * what costs a domain its reputation. A soft bounce is a full mailbox or a
 * server having a bad afternoon, and suppressing on one would throw away a good
 * lead for somebody's holiday backlog.
 */
class BounceReport
{
    public function __construct(
        public readonly string $recipient,
        public readonly ?string $originalMessageId,
        public readonly bool $isHard,
        public readonly string $diagnostic,
    ) {}
}
