<?php

namespace App\Jobs;

use App\Ai\Agents\ReplyHandler;
use App\Models\Message;
use App\Support\CurrentProject;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Asking the agent what one reply means. On the `ai` queue, away from the
 * mailbox polling that found it: a provider rate limit must not stop us reading
 * the rest of somebody's inbox.
 *
 * The sequence is already paused by the time this runs, so a failure here costs
 * a decision and never a mail sent to somebody who just answered.
 */
class HandleReply implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public function __construct(public Message $reply)
    {
        $this->onQueue('ai');
    }

    public function uniqueId(): string
    {
        return (string) $this->reply->id;
    }

    public function handle(CurrentProject $currentProject): void
    {
        $currentProject->run($this->reply->lead->project, function (): void {
            $agent = new ReplyHandler($this->reply->lead->project, $this->reply);

            // The mail it answers travels with it: "yes, that works" means
            // nothing on its own, and the step's intent is what makes the
            // difference between an agreement and a brush-off.
            $agent->prompt($this->prompt());
        });
    }

    private function prompt(): string
    {
        $ours = $this->reply->campaignLead
            ?->messages()
            ->where('message_id', $this->reply->in_reply_to)
            ->first();

        $lead = $this->reply->lead;
        $context = [
            'from' => $lead->email,
            'their_name' => mb_trim($lead->first_name.' '.$lead->last_name) ?: null,
            'their_company' => $lead->company?->name,
            'we_wrote' => $ours === null ? null : [
                'subject' => $ours->subject,
                'body' => $ours->body,
            ],
        ];

        $json = (string) json_encode($context, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        return "Their reply:\nSubject: {$this->reply->subject}\n\n{$this->reply->body}\n\n---\n\nContext:\n{$json}";
    }
}
