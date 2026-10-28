<?php

namespace App\Actions;

use App\Ai\Agents\MeetingProposalWriter;
use App\Models\CampaignLead;
use App\Models\Message;
use App\Services\Meetings\Availability;
use Carbon\CarbonImmutable;
use RuntimeException;

/**
 * The answer to somebody who said yes, drafted for the user to edit: three
 * free times from their hours, written up in the lead's language. Nothing is
 * sent here. The draft goes back to the reply box, and the user sends it
 * through `ReplyToConversation` like anything else they write.
 *
 * Run when the user asks for it, not when the reply arrives: times picked then
 * would be stale by the time somebody opens the conversation, and a draft
 * nobody wanted would cost a call for nothing.
 */
class DraftMeetingProposal
{
    public function __construct(private Availability $availability) {}

    /**
     * @return array{body: string, slots: list<string>}
     */
    public function handle(CampaignLead $conversation): array
    {
        $project = $conversation->campaign->project;

        if (! $project->takesMeetings()) {
            throw new RuntimeException('Set the hours you take calls first, in Settings > Meetings.');
        }

        $reply = $conversation->messages->last(fn (Message $message): bool => $message->direction->isInbound());

        if ($reply === null) {
            throw new RuntimeException('They have not replied yet, so there is nothing to answer.');
        }

        $slots = $this->availability->slots($project);

        if ($slots === []) {
            throw new RuntimeException('No free time in your meeting hours over the next three weeks.');
        }

        $zone = (string) $project->meeting_timezone;

        $response = (new MeetingProposalWriter(
            $project,
            $reply,
            array_map(fn (CarbonImmutable $slot): string => $slot->setTimezone($zone)->format('l j F Y, H:i'), $slots),
            $project->meeting_duration_minutes,
            $zone,
            $project->meeting_location,
        ))->write();

        $offered = array_map(fn (CarbonImmutable $slot): string => $slot->toIso8601String(), $slots);

        // Kept so that once the lead picks one, booking it is a click rather
        // than retyping a date the draft already held.
        $conversation->update(['proposed_meeting_slots' => $offered]);

        return [
            'body' => mb_trim((string) ($response->structured['body'] ?? '')),
            'slots' => $offered,
        ];
    }
}
