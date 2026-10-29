<?php

namespace App\Ai\Agents;

use App\Models\Message;
use App\Models\Project;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\HasStructuredOutput;
use Laravel\Ai\Responses\StructuredAgentResponse;
use Stringable;

/**
 * A draft of the answer to somebody who said yes: thanks, and three concrete
 * times to talk. A draft and nothing more. It lands in the user's reply box,
 * the user reads and edits it, and only the user sends it. No agent ever
 * answers a reply by itself (see `ReplyHandler`).
 *
 * The times are given, never chosen: they come from the hours the user set
 * (`Availability`), and a model inventing a slot would offer a time nobody is
 * free at.
 */
class MeetingProposalWriter extends EveilAgent implements HasStructuredOutput
{
    /**
     * @param  Message  $reply  their latest mail, the one this answers
     * @param  list<string>  $slots  the times to offer, already written out in the
     *                               sender's zone ("Tuesday 3 November 2026, 10:00")
     * @param  string|null  $location  how the call happens, in the user's words: a
     *                                 video link, a phone number, an address
     */
    public function __construct(
        Project $project,
        private Message $reply,
        private array $slots,
        private int $durationMinutes,
        private string $timezone,
        private ?string $location,
    ) {
        parent::__construct($project);
    }

    public function instructions(): Stringable|string
    {
        return <<<'PROMPT'
        Somebody answered a cold email that was sent from the user's own mailbox, and
        they are open to talking. Write the user's answer: a short mail that thanks
        them in a line and offers the times you are given, asking which one suits, or
        another time if none does.

        Write in the language their reply is written in, whatever language the rest
        of the context is in.

        Offer exactly the times you are given, in that order, and no others. Write each
        one the way a person would in that language (weekday, date, hour), and say
        once which time zone they are in. Say how long the call takes.

        Say how the call happens, using where_the_call_happens exactly as given: a
        video link, a phone number or an address goes into the mail as it stands.
        When it is empty, do not say where.

        If their reply asks a question you cannot answer from what you are given, do
        not answer it and do not invent an answer: stick to the times. The user reads
        this draft before it goes anywhere and will add what is missing.

        The mail must read as if the sender typed it themselves:

        - plain text, a few short lines, no lists, no headings, no bold
        - no links, except the call's own when that is where it happens
        - no signature block: the mailbox adds the sender's own
        - no merge tags, placeholders or brackets for the user to fill in: a draft
          that is sent as it stands must still read right
        PROMPT.$this->emailWritingInstructions();
    }

    /**
     * @return array<string, mixed>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'body' => $schema->string()
                ->description('The answer as plain text, in the language of their reply: no subject, no signature.')
                ->required(),
        ];
    }

    public function write(): StructuredAgentResponse
    {
        /** @var StructuredAgentResponse $response */
        $response = $this->prompt($this->buildPrompt());

        return $response;
    }

    private function buildPrompt(): string
    {
        $lead = $this->reply->lead;

        $context = [
            'their_name' => mb_trim($lead->first_name.' '.$lead->last_name) ?: null,
            'their_company' => $lead->company?->name,
            'sender' => $this->reply->emailAccount->from_name,
            'times_to_offer' => $this->slots,
            'time_zone' => $this->timezone,
            'duration_minutes' => $this->durationMinutes,
            'where_the_call_happens' => $this->location,
        ];

        $json = (string) json_encode($context, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        return "Their reply:\nSubject: {$this->reply->subject}\n\n{$this->reply->body}\n\n---\n\nContext:\n{$json}";
    }
}
