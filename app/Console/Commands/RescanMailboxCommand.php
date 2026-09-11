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
 *
 * One call is not one pass: `ImapClient::fetchSince()` caps itself at 50
 * mails so a first run on a busy address never hangs. This loops it until a
 * batch comes back empty (caught up to the tip) or the `--max-batches`
 * ceiling trips, rather than leaving "rewind and reread" secretly meaning
 * "reread the next 50" and stranding the operator mid-mailbox.
 */
class RescanMailboxCommand extends Command
{
    protected $signature = 'eveil:rescan-mailbox {mailbox : Email account id} {--force : Skip the confirmation} {--max-batches=50 : Safety cap on IMAP round-trips (50 mails each)}';

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

        $totalAttributed = 0;
        $maxBatches = (int) $this->option('max-batches');

        for ($batch = 1; $batch <= $maxBatches; $batch++) {
            $before = $account->last_inbound_uid;

            // `FetchReplies::handle()` swallows an `ImapFailure` itself (sets
            // the account's own `status`/`last_error` and returns 0): a fetch
            // failing is the same problem to the user whether it came from a
            // poll or a manual rescan, not two. Mutates `$account` in place,
            // no refetch needed to see it.
            $totalAttributed += $fetch->handle($account);

            if ($account->status === EmailAccountStatus::Error) {
                $this->error("Could not reach the mailbox: {$account->last_error}");

                return self::FAILURE;
            }

            // Cursor did not move: the batch came back empty, so this is the
            // tip of the mailbox and every pass after this one would do
            // nothing.
            if ($account->last_inbound_uid === $before) {
                $this->info("Caught up. {$totalAttributed} repl".($totalAttributed === 1 ? 'y' : 'ies')." attributed over {$batch} batch(es). Final cursor: UID {$account->last_inbound_uid}.");

                return self::SUCCESS;
            }

            $this->line("Batch {$batch}: cursor now UID {$account->last_inbound_uid}.");
        }

        $this->warn("Stopped at the {$maxBatches}-batch ceiling, mailbox may not be fully read. {$totalAttributed} repl".($totalAttributed === 1 ? 'y' : 'ies')." attributed so far. Cursor: UID {$account->last_inbound_uid}. Rerun to continue.");

        return self::SUCCESS;
    }
}
