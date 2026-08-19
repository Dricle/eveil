<?php

namespace App\Enums;

/**
 * What an incoming reply turned out to be. Written by the tool the reply agent
 * called, never by a second classifying pass — the decision and the record are
 * the same act.
 *
 * This is a compliance mechanism before it is a metric: replying is the only
 * opt-out channel this product offers, because mails carry no unsubscribe link
 * and no `List-Unsubscribe` header.
 */
enum ReplyClassification: string
{
    case Interested = 'interested';
    case NotNow = 'not_now';
    case WrongPerson = 'wrong_person';
    case NotInterested = 'not_interested';
    case NeedsHuman = 'needs_human';
    case Unsubscribe = 'unsubscribe';
    case AutoReply = 'auto_reply';

    /**
     * Errs toward suppressing on purpose: a false positive costs one lead, a
     * false negative costs a complaint.
     */
    public function shouldSuppress(): bool
    {
        return $this === self::Unsubscribe;
    }

    /**
     * An out-of-office must never pause a campaign — otherwise a fortnight's
     * holiday reads as a reply.
     */
    public function shouldPauseCampaign(): bool
    {
        return $this !== self::AutoReply;
    }

    /**
     * The north metric. Raw reply rate counts "no thanks" and
     * out-of-office alongside real interest, which is why it is not used.
     *
     * `NeedsHuman` deliberately does NOT count. A precise question or an
     * ambiguous answer needs somebody to write back, and calling that a
     * positive reply is exactly the inflation this metric exists to refuse —
     * it still sits at the top of the inbox, which is where it is useful.
     */
    public function isPositive(): bool
    {
        return $this === self::Interested;
    }

    /**
     * Whether a person has to read this one before anything else happens.
     */
    public function needsAttention(): bool
    {
        return in_array($this, [self::Interested, self::NeedsHuman, self::WrongPerson], strict: true);
    }
}
