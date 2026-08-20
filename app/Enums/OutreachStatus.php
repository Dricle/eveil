<?php

namespace App\Enums;

/**
 * Where a company or a person stands, in one vocabulary for both.
 *
 * Deliberately not two enums. A company and the people at it are the same
 * relationship seen from two ends: marking a company as an existing client
 * says something about every address at it, and closing a deal with one person
 * closes it for the company. Two vocabularies would need a mapping between
 * them, and the mapping has holes in both directions: no `rejected` on a
 * person, no `replied` on a company. `App\Actions\SetOutreachStatus` copies the
 * value across instead.
 *
 * The first four are written by the app as outreach runs; the rest are the
 * user's own verdict, except `Suppressed`, which is an unsubscribe or an
 * erasure and is the one status that never propagates.
 */
enum OutreachStatus: string
{
    case New = 'new';
    case Queued = 'queued';
    case Contacted = 'contacted';
    case Replied = 'replied';
    case Won = 'won';
    case Lost = 'lost';
    case Client = 'client';
    case Rejected = 'rejected';
    case Suppressed = 'suppressed';

    /**
     * The statuses that take a company or a person out of outreach. A client
     * already buys, a won deal is closed, a lost one said no, a rejected one
     * was never wanted, and a suppressed one asked us to stop: cold-mailing
     * any of the five is the mistake this list prevents.
     *
     * @return array<int, self>
     */
    public static function excluded(): array
    {
        return [self::Won, self::Lost, self::Client, self::Rejected, self::Suppressed];
    }

    public function isExcluded(): bool
    {
        return in_array($this, self::excluded(), strict: true);
    }
}
