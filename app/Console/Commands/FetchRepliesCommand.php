<?php

namespace App\Console\Commands;

use App\Enums\EmailAccountStatus;
use App\Jobs\FetchMailboxReplies;
use App\Models\EmailAccount;
use Illuminate\Console\Command;

/**
 * Checking every working mailbox for replies.
 *
 * Paused and errored mailboxes are read too, deliberately: a mailbox stopped
 * because its sends were bouncing still receives the answers to what already
 * went out, and an opt-out arriving there has to be honoured whatever state the
 * sending half is in.
 */
class FetchRepliesCommand extends Command
{
    protected $signature = 'eveil:fetch-replies {--mailbox= : Only this mailbox id}';

    protected $description = 'Read new replies out of every mailbox and act on them';

    public function handle(): int
    {
        $mailboxes = EmailAccount::query()
            ->when($this->option('mailbox'), fn ($query, $id) => $query->whereKey($id))
            ->whereIn('status', [EmailAccountStatus::Active, EmailAccountStatus::Paused, EmailAccountStatus::Error])
            ->get();

        foreach ($mailboxes as $mailbox) {
            FetchMailboxReplies::dispatch($mailbox);
        }

        $this->info("Queued a read of {$mailboxes->count()} mailbox(es).");

        return self::SUCCESS;
    }
}
