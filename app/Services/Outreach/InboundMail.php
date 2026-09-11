<?php

namespace App\Services\Outreach;

/**
 * One mail read out of a mailbox, reduced to what answering it needs.
 *
 * Not an Eloquent model: most of what is fetched belongs to nobody we wrote to
 *. Newsletters, invoices, a colleague, and only the ones that attribute to a
 * lead ever become rows.
 */
class InboundMail
{
    /**
     * @param  list<string>  $referenceIds  Every candidate parent id (In-Reply-To + References), nearest first. Used for attribution.
     */
    public function __construct(
        public readonly int $uid,
        public readonly string $messageId,
        public readonly ?string $inReplyTo,
        public readonly array $referenceIds,
        public readonly string $from,
        public readonly string $subject,
        public readonly string $body,
        /** The untouched RFC 5322 message: the safety net a `MailParser` bug backfills from. */
        public readonly string $rawSource,
        public readonly bool $isAutoReply,
        /** Set when the mail is a delivery failure rather than an answer. */
        public readonly ?BounceReport $bounce = null,
    ) {}
}
