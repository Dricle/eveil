<?php

namespace App\Services\Outreach;

use RuntimeException;

/**
 * A send that did not happen, sorted by what the caller has to do about it:
 * which is a different thing in each case and must not be collapsed:
 *
 *   Recipient. The address is dead. Suppress it and move on; retrying is what
 *               ruins a sender's reputation.
 *   Auth: the MAILBOX is broken, not the address. Pause it before it
 *               burns through a campaign failing on every lead. A refusal
 *               about the FROM address belongs here, whatever code carries it.
 *   Transient: greylisting, a rate limit, a network blip. Try later, same
 *               address, no state changed.
 */
class SendFailure extends RuntimeException
{
    private function __construct(public readonly string $kind, string $message)
    {
        parent::__construct($message);
    }

    public static function fromTransportError(string $error): self
    {
        $lower = mb_strtolower($error);

        $kind = match (true) {
            str_contains($lower, 'authentication'), str_contains($lower, '535'),
            str_contains($lower, '534'), str_contains($lower, 'smtp auth'),
            str_contains($lower, 'app password') => 'auth',

            // A refusal about the SENDER, which several providers report with
            // the same 5xx codes they use for a bad recipient. Zoho answers
            // "553 Sender is not allowed to relay emails" when the From address
            // is not verified on the account, and reading that as a dead
            // recipient suppresses an innocent prospect for ever while the real
            // fault is one setting away.
            //
            // Checked BEFORE the recipient codes on purpose. The two mistakes
            // do not cost the same: a mailbox wrongly paused shows the server's
            // own words and is undone in a click, while an address wrongly
            // suppressed is silent and meant to be permanent.
            str_contains($lower, 'relay'),
            str_contains($lower, 'sender is not allowed'),
            str_contains($lower, 'sender address rejected'),
            str_contains($lower, 'sender not authorized'),
            str_contains($lower, 'sender verify failed'),
            str_contains($lower, 'not owned by user') => 'auth',

            // 5xx about the recipient, once the sender-side refusals above
            // have been ruled out. `550`, `551` and `553` are "no such
            // address"; `552` is a full mailbox, which is transient and
            // deliberately not in this list.
            str_contains($lower, '550'), str_contains($lower, '551'),
            str_contains($lower, '553'), str_contains($lower, 'user unknown'),
            str_contains($lower, 'recipient rejected'),
            str_contains($lower, 'does not exist') => 'recipient',

            default => 'transient',
        };

        return new self($kind, $error);
    }
}
