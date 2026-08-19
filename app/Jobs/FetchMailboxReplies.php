<?php

namespace App\Jobs;

use App\Actions\FetchReplies;
use App\Models\EmailAccount;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Polling one mailbox. On the `imap` queue, which exists so that a provider
 * being slow to answer never holds up sending or discovery.
 *
 * Unique per mailbox: two workers reading the same INBOX would both fetch from
 * the same UID and race to advance it, and the loser's mails would be recorded
 * twice or not at all.
 */
class FetchMailboxReplies implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public function __construct(public EmailAccount $account)
    {
        $this->onQueue('imap');
    }

    public function uniqueId(): string
    {
        return (string) $this->account->id;
    }

    public function handle(FetchReplies $fetch): void
    {
        $fetch->handle($this->account);
    }
}
