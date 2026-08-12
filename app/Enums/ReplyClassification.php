<?php

namespace App\Enums;

/**
 * Reply classification is a compliance mechanism, not a metric:
 * "reply STOP" is the only opt-out channel this product offers, because mails
 * carry no unsubscribe link and no `List-Unsubscribe` header.
 */
enum ReplyClassification: string
{
    case Interested = 'interested';
    case NotNow = 'not_now';
    case WrongPerson = 'wrong_person';
    case NotInterested = 'not_interested';
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
     */
    public function isPositive(): bool
    {
        return $this === self::Interested;
    }
}
