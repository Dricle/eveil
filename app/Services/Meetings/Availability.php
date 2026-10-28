<?php

namespace App\Services\Meetings;

use App\Models\Meeting;
use App\Models\Project;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;

/**
 * The times a reply can offer, read from the weekly hours the user set and
 * nothing else: no calendar is connected, so a slot is free unless a meeting
 * this project already booked sits on it.
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
     * The first `$count` free slots, starting tomorrow in the project's zone:
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

        $booked = Meeting::query()
            ->where('project_id', $project->id)
            ->where('starts_at', '>=', $now->subDay())
            ->get(['starts_at', 'duration_minutes']);

        $hours = collect($project->meeting_hours ?? [])
            ->sortBy(fn (array $window): string => $window['start']);

        $slots = [];
        $day = $now->setTimezone($zone)->startOfDay()->addDay();

        for ($i = 0; $i < self::HORIZON_DAYS && count($slots) < $count; $i++, $day = $day->addDay()) {
            foreach ($hours->where('day', $day->dayOfWeekIso) as $window) {
                $slot = $this->firstFree($day, $window, $duration, $booked->all());

                if ($slot !== null) {
                    $slots[] = $slot->utc();

                    continue 2;
                }
            }
        }

        return $slots;
    }

    /**
     * @param  array{day: int, start: string, end: string}  $window
     * @param  array<int, Meeting>  $booked
     */
    private function firstFree(CarbonImmutable $day, array $window, int $duration, array $booked): ?CarbonImmutable
    {
        $end = $day->setTimeFromTimeString($window['end']);

        for ($start = $day->setTimeFromTimeString($window['start']); $start->addMinutes($duration) <= $end; $start = $start->addMinutes($duration)) {
            $finish = $start->addMinutes($duration);

            $taken = collect($booked)->contains(
                fn (Meeting $meeting): bool => $start < $meeting->endsAt() && $finish > $meeting->starts_at
            );

            if (! $taken) {
                return $start;
            }
        }

        return null;
    }
}
