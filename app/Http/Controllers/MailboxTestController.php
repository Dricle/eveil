<?php

namespace App\Http\Controllers;

use App\Enums\EmailAccountStatus;
use App\Models\EmailAccount;
use App\Services\Outreach\MailboxTester;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Trying the mailbox for real, and saying what went wrong in the words of the
 * thing that has to change. A generic "authentication failed" is where a signup
 * ends: the user cannot tell a wrong password from an admin policy that
 * forbids app passwords, and only one of those is theirs to fix.
 *
 * A working test also clears an error state: a mailbox paused because its
 * password expired is usable again the moment the new one answers, and nobody
 * should have to guess that.
 */
class MailboxTestController extends Controller
{
    public function store(Request $request, MailboxTester $tester, int $mailbox): RedirectResponse
    {
        $mailbox = EmailAccount::query()->ownedBy($request->user())->findOrFail($mailbox);

        $problem = $tester->test($mailbox);

        $mailbox->update([
            'last_checked_at' => now(),
            'last_error' => $problem,
            'status' => $problem === null
                ? EmailAccountStatus::Active
                // Not `Paused`: paused is a choice somebody made, error is the
                // mailbox telling us it cannot work.
                : EmailAccountStatus::Error,
        ]);

        return $problem === null
            ? back()->with('status', 'The mailbox answered on both SMTP and IMAP.')
            : back()->withErrors(['test' => $problem]);
    }
}
