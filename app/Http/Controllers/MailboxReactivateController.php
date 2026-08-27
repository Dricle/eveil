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
 * again. A fresh bounce still has real bounces behind it, and the circuit
 * breaker trips again if the rate is still over threshold once enough sends
 * have gone out to judge it.
 */
class MailboxReactivateController extends Controller
{
    public function store(Request $request, int $mailbox): RedirectResponse
    {
        $mailbox = EmailAccount::query()->ownedBy($request->user())->findOrFail($mailbox);

        $mailbox->update([
            'status' => EmailAccountStatus::Active,
            'last_error' => null,
        ]);

        return back()->with('status', 'Mailbox reactivated.');
    }
}
