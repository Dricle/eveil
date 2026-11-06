<?php

namespace App\Enums;

/**
 * Where one recommendation stands, in the vocabulary the API and Evie's
 * tools use. Stored as the `InsightStatus` of its `insights` row: `Proposed` is
 * open, `Done` is used, `Archived` is dismissed. `Proposed` is the only
 * state a re-analysis may write: once the user has decided, `Done` or
 * `Archived` sticks, and `Archived` never comes back (ADR-032).
 */
enum RecommendationStatus: string
{
    case Proposed = 'proposed';
    case Done = 'done';
    case Archived = 'archived';

    public function toInsightStatus(): InsightStatus
    {
        return match ($this) {
            self::Proposed => InsightStatus::Open,
            self::Done => InsightStatus::Used,
            self::Archived => InsightStatus::Dismissed,
        };
    }

    public static function fromInsightStatus(InsightStatus $status): self
    {
        return match ($status) {
            InsightStatus::Open => self::Proposed,
            InsightStatus::Used => self::Done,
            InsightStatus::Dismissed => self::Archived,
        };
    }
}
