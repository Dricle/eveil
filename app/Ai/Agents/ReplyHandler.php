<?php

namespace App\Ai\Agents;

use App\Ai\Tools\AskForRightContact;
use App\Ai\Tools\IgnoreReply;
use App\Ai\Tools\MarkNeedsHuman;
use App\Ai\Tools\MarkNotInterested;
use App\Ai\Tools\RescheduleFollowUp;
use App\Ai\Tools\SuppressLead;
use App\Models\Message;
use App\Models\Project;
use Laravel\Ai\Attributes\MaxSteps;
use Laravel\Ai\Contracts\HasTools;
use Laravel\Ai\Responses\AgentResponse;
use Stringable;

/**
 * What to do about one reply. The agent reads it and acts through its tools;
 * nothing downstream re-decides afterwards.
 *
 * Not a classifier returning a label. A label would need a second pass to act
 * on, would not know that "speak to my colleague" ends this person's sequence
 * but not the company's, and would give the same weight to "no thanks" as to
 * "please stop writing to me", which are one lead and one complaint
 * respectively.
 *
 * It never writes a reply. The whole promise is that these mails read as one
 * person writing to another, and an agent answering a real question would end
 * that in a single message; an interested reply goes to the top of the user's
 * inbox instead.
 */
#[MaxSteps(3)]
class ReplyHandler extends EveilAgent implements HasTools
{
    public function __construct(Project $project, private Message $reply)
    {
        parent::__construct($project);
    }

    public function instructions(): Stringable|string
    {
        return <<<'PROMPT'
        You are given one reply to a cold email that was sent from the user's own
        mailbox, along with the mail it answers. Decide what happens to it, and
        act by calling exactly one tool.

        Read what the person actually means, not the words they used. A reply is
        rarely a clean category: "thanks but we're sorted for now, maybe next
        year" is a postponement, "please take me off this" is an opt-out, and
        "who is this?" needs a human. Answer in their language internally: the
        mail is written in theirs, not yours.

        The one asymmetry that matters: writing again to somebody who asked you
        to stop is a complaint against the sender's own domain and cannot be
        undone, while suppressing somebody who only meant "no thanks" costs a
        single lead. When you hesitate between suppress_lead and
        mark_not_interested, suppress.

        Never treat a short or blunt human reply as automatic. "No." is a person
        answering; only a machine's message is ignore_reply, and calling it
        resumes the sequence.

        You do not write replies and you have no tool to send one. When the
        answer needs words, that is mark_needs_human and the user writes them.

        Call one tool. Do not explain yourself in prose afterwards.
        PROMPT;
    }

    /**
     * The whole decision surface. Each one carries the reply it acts on, so the
     * model chooses WHAT happens and never which row it happens to.
     *
     * @return array<int, object>
     */
    public function tools(): iterable
    {
        return [
            new SuppressLead($this->reply),
            new MarkNotInterested($this->reply),
            new MarkNeedsHuman($this->reply),
            new RescheduleFollowUp($this->reply),
            new AskForRightContact($this->reply),
            new IgnoreReply($this->reply),
        ];
    }

    /**
     * Reads the reply (already held on the agent) and acts by calling exactly
     * one tool. Nothing meaningful in the return value - the tool call is the
     * whole effect - but it is exposed for the caller to await/record.
     */
    public function decide(): AgentResponse
    {
        return $this->prompt($this->buildPrompt());
    }

    /**
     * The mail it answers travels with it: "yes, that works" means nothing on
     * its own, and the step's intent is what makes the difference between an
     * agreement and a brush-off.
     */
    private function buildPrompt(): string
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
