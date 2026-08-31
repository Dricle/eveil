<?php

namespace App\Http\Controllers;

use App\Enums\EmailAccountStatus;
use App\Models\EmailAccount;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Undoing a pause, whatever tripped it. `Paused` and `Error` both mean the
 * mailbox stopped sending: an auth failure, a full mailbox, or the bounce
 * circuit breaker in `DispatchDueSends`. None of those are connection
 * problems, so this does not re-test SMTP/IMAP the way `MailboxTestController`
 * does; it just admits the operator has looked and the address is fine to try
 * again.
 *
 * Also resets `bounce_window_reset_at`: without it, a mailbox whose all-time
 * history is still over the bounce threshold re-pauses itself on the very
 * next dispatch tick, before a single new mail can leave, no new bounce
 * required. The breaker only ever judges what happens from here on.
 */
class MailboxReactivateController extends Controller
{
    public function store(Request $request, int $mailbox): RedirectResponse
    {
        $mailbox = EmailAccount::query()->ownedBy($request->user())->findOrFail($mailbox);

        $mailbox->update([
            'status' => EmailAccountStatus::Active,
            'last_error' => null,
            // The bounce breaker judges only what happens from here on: without
            // this, a mailbox whose all-time history is still over threshold
            // re-pauses itself on the next dispatch tick, no new bounce needed.
            'bounce_window_reset_at' => now(),
        ]);

        return back()->with('status', 'Mailbox reactivated.');
    }
}
