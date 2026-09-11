<?php

namespace App\Console\Commands;

use App\Actions\FetchReplies;
use App\Enums\EmailAccountStatus;
use App\Models\EmailAccount;
use Illuminate\Console\Command;

/**
 * Recovers a reply that was never attributed the first time: a code bug in
 * attribution, a worker that crashed mid-fetch, or anything else that let a
 * mail slip past `last_inbound_uid` without ever becoming a `Message` row.
 *
 * `last_inbound_uid` always advances, even on a mail that fails to attribute
 * (`FetchReplies::handle()`), so the ordinary poll never revisits it. This
 * resets the cursor and reruns attribution with whatever `FetchReplies` does
 * TODAY, which is the whole point after a fix ships. Safe to rerun: the
 * unique index on `message_id` skips anything already recorded.
 */
class RescanMailboxCommand extends Command
{
    protected $signature = 'eveil:rescan-mailbox {mailbox : Email account id} {--force : Skip the confirmation}';

    protected $description = 'Rewind one mailbox\'s IMAP cursor and reread every mail, to recover a reply that failed to attribute';

    public function handle(FetchReplies $fetch): int
    {
        $account = EmailAccount::query()->find($this->argument('mailbox'));

        if ($account === null) {
            $this->error('No email account with that id.');

            return self::FAILURE;
        }

        // Rewinding to the very start rereads the mailbox's whole history,
        // which is fine for the dedup but can be slow or noisy on a shared
        // inbox with years of mail, hence the confirmation.
        if (! $this->option('force') && ! $this->confirm(
            "Rewind {$account->from_email} (currently at UID {$account->last_inbound_uid}) and reread the whole mailbox?"
        )) {
            return self::FAILURE;
        }

        $account->update(['last_inbound_uid' => null]);

        // `FetchReplies::handle()` swallows an `ImapFailure` itself (sets the
        // account's own `status`/`last_error` and returns 0): a fetch failing
        // is the same problem to the user whether it came from a poll or a
        // manual rescan, not two. Mutates `$account` in place, no refetch
        // needed to see it.
        $attributed = $fetch->handle($account);

        if ($account->status === EmailAccountStatus::Error) {
            $this->error("Could not reach the mailbox: {$account->last_error}");

            return self::FAILURE;
        }

        $this->info("Done. {$attributed} repl".($attributed === 1 ? 'y' : 'ies').' attributed. New cursor: UID '.$account->last_inbound_uid.'.');

        return self::SUCCESS;
    }
}
