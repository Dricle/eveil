<?php

namespace App\Actions;

use App\Enums\CampaignLeadStatus;
use App\Enums\MessageDirection;
use App\Enums\MessageStatus;
use App\Models\CampaignLead;
use App\Models\EmailAccount;
use App\Models\Message;
use App\Services\Outreach\Sender;
use App\Services\Outreach\SendFailure;
use RuntimeException;

/**
 * Sending the user's own answer into an existing thread.
 *
 * Through the mailbox the sequence pinned, never another one: the recipient is
 * answering a conversation with a person at an address, and a reply from a
 * different address is a different person as far as they and their spam filter
 * are concerned.
 *
 * The sequence stays stopped afterwards. Somebody who is being written to by
 * hand must not also receive the automated follow-up that was queued behind
 * them: that is the mistake this whole screen exists to prevent.
 */
class ReplyToConversation
{
    public function __construct(private Sender $sender) {}

    public function handle(CampaignLead $conversation, string $body): Message
    {
        $account = $conversation->emailAccount;

        if ($account === null) {
            throw new RuntimeException('This conversation has no mailbox to answer from.');
        }

        $thread = $conversation->messages->last();
        $subject = $this->subject($conversation);

        try {
            $messageId = $this->sender->send(
                $account,
                $conversation->lead,
                $subject,
                $body,
                $thread?->message_id,
                $thread === null ? null : $this->quote($thread, $conversation, $account),
            );
        } catch (SendFailure $failure) {
            // Surfaced rather than swallowed: the user is standing in front of
            // the screen and has to know their answer did not leave.
            throw new RuntimeException($failure->getMessage(), previous: $failure);
        }

        $reply = Message::query()->create([
            'lead_id' => $conversation->lead_id,
            'campaign_lead_id' => $conversation->id,
            'email_account_id' => $account->id,
            'direction' => MessageDirection::Outbound,
            'message_id' => $messageId,
            'in_reply_to' => $thread?->message_id,
            'subject' => $subject,
            'body' => $body,
            'status' => MessageStatus::Sent,
            'sent_at' => now(),
        ]);

        $conversation->update([
            'status' => CampaignLeadStatus::Stopped,
            'pause_reason' => 'answered_by_hand',
            'next_action_at' => null,
        ]);

        return $reply;
    }

    /**
     * The thread's own subject, prefixed once. "Re: Re: Re:" is what a machine
     * looks like.
     */
    private function subject(CampaignLead $conversation): string
    {
        $last = $conversation->messages->last();
        $subject = $last === null ? 'Re:' : $last->subject;

        return str_starts_with(mb_strtolower($subject), 're:') ? $subject : 'Re: '.$subject;
    }

    /**
     * The message being answered, quoted underneath the reply the way every
     * mail client does it by hand: "On <date>, <name> wrote:" followed by the
     * body with each line prefixed `>`. Without it the reply lands in the
     * recipient's client as a standalone mail instead of a threaded one.
     */
    private function quote(Message $thread, CampaignLead $conversation, EmailAccount $account): string
    {
        $when = ($thread->sent_at ?? $thread->received_at ?? $thread->created_at)?->format('D, M j, Y \a\t g:i A');

        [$name, $email] = $thread->direction->isInbound()
            ? [mb_trim($conversation->lead->first_name.' '.$conversation->lead->last_name) ?: $conversation->lead->email, $conversation->lead->email]
            : [$account->from_name, $account->from_email];

        $quotedBody = collect(explode("\n", mb_trim($thread->body)))
            ->map(fn (string $line): string => '> '.$line)
            ->implode("\n");

        return "On {$when}, {$name} <{$email}> wrote:\n{$quotedBody}";
    }
}
