<?php

namespace App\Services\Outreach;

use RuntimeException;

/**
 * A send that did not happen, sorted by what the caller has to do about it —
 * which is a different thing in each case and must not be collapsed:
 *
 *   Recipient — the address is dead. Suppress it and move on; retrying is what
 *               ruins a sender's reputation.
 *   Auth      — the MAILBOX is broken, not the address. Pause it before it
 *               burns through a campaign failing on every lead.
 *   Transient — greylisting, a rate limit, a network blip. Try later, same
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

            // 5xx about the recipient. `550`, `551` and `553` are "no such
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
