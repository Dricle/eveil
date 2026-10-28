<?php

namespace App\Services\Meetings;

use App\Models\EmailAccount;
use App\Models\Lead;
use App\Models\Meeting;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;

/**
 * The `.ics` a person attaches when they send somebody an invite: one event,
 * `METHOD:REQUEST`, organised by the mailbox the conversation runs through and
 * addressed to the lead, so their calendar offers to add it and their answer
 * comes back to that same mailbox.
 *
 * Nothing in it names this application. `PRODID` is required by the format and
 * is read by receiving servers like any other header, so it names the sender's
 * own domain, the same reasoning as the Message-ID `Sender` writes.
 *
 * Times are written in UTC, which every calendar converts to its owner's zone
 * without the VTIMEZONE block a local time would need.
 */
class Invitation
{
    public function ics(Meeting $meeting, EmailAccount $account, Lead $lead): string
    {
        $domain = mb_substr(mb_strrchr($account->from_email, '@') ?: '@localhost', 1);
        $leadName = mb_trim($lead->first_name.' '.$lead->last_name) ?: (string) $lead->email;

        $lines = [
            'BEGIN:VCALENDAR',
            'VERSION:2.0',
            'PRODID:-//'.$domain.'//EN',
            'CALSCALE:GREGORIAN',
            'METHOD:REQUEST',
            'BEGIN:VEVENT',
            'UID:'.$meeting->uid,
            'DTSTAMP:'.$this->utc(now()),
            'DTSTART:'.$this->utc($meeting->starts_at),
            'DTEND:'.$this->utc($meeting->endsAt()),
            'SUMMARY:'.$this->text($account->from_name.' / '.$leadName),
            ...($meeting->location === null ? [] : ['LOCATION:'.$this->text($meeting->location)]),
            'ORGANIZER;CN='.$this->parameter($account->from_name).':mailto:'.$account->from_email,
            'ATTENDEE;CN='.$this->parameter($leadName).';ROLE=REQ-PARTICIPANT;PARTSTAT=NEEDS-ACTION;RSVP=TRUE:mailto:'.$lead->email,
            'SEQUENCE:0',
            'STATUS:CONFIRMED',
            'END:VEVENT',
            'END:VCALENDAR',
        ];

        return implode("\r\n", array_map($this->fold(...), $lines))."\r\n";
    }

    private function utc(CarbonInterface $at): string
    {
        return CarbonImmutable::instance($at)->utc()->format('Ymd\THis\Z');
    }

    /**
     * A TEXT value: backslash, semicolon, comma and line breaks escaped, or a
     * calendar cuts the summary at the first comma.
     */
    private function text(string $value): string
    {
        return str_replace(
            ['\\', ';', ',', "\r\n", "\n"],
            ['\\\\', '\;', '\,', '\n', '\n'],
            $value,
        );
    }

    /**
     * A parameter value such as `CN`: quoted, since a display name often
     * carries the comma or colon that would otherwise end it.
     */
    private function parameter(string $value): string
    {
        return '"'.str_replace('"', '', $value).'"';
    }

    /**
     * Lines longer than 75 octets continue on the next line after a single
     * space. Cut on character boundaries, so a multibyte name is never split
     * into invalid UTF-8.
     */
    private function fold(string $line): string
    {
        $folded = [];
        $current = '';

        foreach (mb_str_split($line) as $character) {
            $limit = $folded === [] ? 75 : 74;

            if (strlen($current.$character) > $limit) {
                $folded[] = $current;
                $current = '';
            }

            $current .= $character;
        }

        $folded[] = $current;

        return implode("\r\n ", $folded);
    }
}
