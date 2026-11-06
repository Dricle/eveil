<?php

namespace App\Enums;

/**
 * What one item of the Insights feed is: an article idea or a competitor
 * mention noted by the Reddit scan, or one of the two recommendation kinds
 * (`RecommendationKind` carries the same values, for the API and Evie's
 * tools, which still speak of "recommendations").
 */
enum InsightKind: string
{
    case Article = 'article';
    case Acquisition = 'acquisition';
    case Feature = 'feature';
    case CompetitorMention = 'competitor_mention';

    /**
     * @return list<self>
     */
    public static function recommendations(): array
    {
        return [self::Acquisition, self::Feature];
    }
}
