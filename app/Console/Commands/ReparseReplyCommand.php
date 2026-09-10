<?php

namespace App\Console\Commands;

use App\Models\Message;
use App\Services\Outreach\ImapClient;
use App\Services\Outreach\ImapFailure;
use Illuminate\Console\Command;

/**
 * Recovers a reply whose stored `body` is wrong: a `MailParser` bug read it
 * wrong the first time, and - before `raw_source` existed to reparse from
 * offline - there is nothing stored to fix it with. This reads the mail again
 * from its mailbox by `Message-ID`, `BODY.PEEK[]` so it is never marked read,
 * and reparses it with whatever `MailParser` does TODAY rather than whatever
 * version read it originally.
 *
 * Only works while the mail server still has the message. Most do for a
 * while; a mailbox with a short retention window has already lost it, and
 * this command says so rather than silently doing nothing.
 */
class ReparseReplyCommand extends Command
{
    protected $signature = 'eveil:reparse-reply {message : The id of the inbound message row to refetch}';

    protected $description = 'Refetch one inbound reply from its mailbox and reparse it with the current MailParser';

    public function handle(ImapClient $imap): int
    {
        $message = Message::query()->with('emailAccount')->find($this->argument('message'));

        if ($message === null || ! $message->direction->isInbound()) {
            $this->error('No inbound message with that id.');

            return self::FAILURE;
        }

        try {
            $mail = $imap->fetchByMessageId($message->emailAccount, $message->message_id);
        } catch (ImapFailure $failure) {
            $this->error("Could not reach the mailbox: {$failure->getMessage()}");

            return self::FAILURE;
        }

        if ($mail === null) {
            $this->error('The mail server no longer has this message - it may have been deleted, or fallen out of the mailbox\'s retention window.');

            return self::FAILURE;
        }

        $message->update(['body' => $mail->body, 'raw_source' => $mail->rawSource]);

        $this->info("Refetched and reparsed. New body:\n\n{$mail->body}");

        return self::SUCCESS;
    }
}
