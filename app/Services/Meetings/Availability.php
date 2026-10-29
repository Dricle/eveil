<?php

namespace App\Services\Meetings;

use App\Models\Project;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;

/**
 * The times a reply can offer, read from the weekly hours the user set and
 * nothing else: no calendar is connected, so every hour they set is free.
 *
 * One slot per day, the earliest free one, on consecutive working days. Three
 * slots on the same morning is not a choice, and it is not how a person
 * offers times either.
 */
class Availability
{
    /**
     * How far ahead to look. Long enough to step over a week the hours leave
     * empty; short enough that an offer is still a near date.
     */
    private const HORIZON_DAYS = 21;

    /**
     * The first `$count` slots, starting tomorrow in the project's zone:
     * an answer is read hours after it is written, and a time offered for
     * this afternoon may be gone by then.
     *
     * @return list<CarbonImmutable> in UTC
     */
    public function slots(Project $project, int $count = 3, ?CarbonInterface $now = null): array
    {
        if (! $project->takesMeetings()) {
            return [];
        }

        $zone = (string) $project->meeting_timezone;
        $duration = max(15, $project->meeting_duration_minutes);
        $now = CarbonImmutable::instance($now ?? now());

        $hours = collect($project->meeting_hours ?? [])
            ->sortBy(fn (array $window): string => $window['start']);

        $slots = [];
        $day = $now->setTimezone($zone)->startOfDay()->addDay();

        for ($i = 0; $i < self::HORIZON_DAYS && count($slots) < $count; $i++, $day = $day->addDay()) {
            // The earliest window that fits a whole call that day.
            foreach ($hours->where('day', $day->dayOfWeekIso) as $window) {
                $start = $day->setTimeFromTimeString($window['start']);

                if ($start->addMinutes($duration) <= $day->setTimeFromTimeString($window['end'])) {
                    $slots[] = $start->utc();

                    continue 2;
                }
            }
        }

        return $slots;
    }
}
