<?php

namespace App\Enums;

/**
 * What one recommendation is about. Both kinds share the same
 * proposed/done/archived lifecycle (ADR-032), only the agent that proposes
 * them differs: `Acquisition` is a lever the Website agent found missing
 * from the site, `Feature` a capability competitors offer that the product
 * does not (`App\Ai\Agents\CompetitorAnalyst`). Stored as an `insights` row of
 * the `InsightKind` with the same value.
 */
enum RecommendationKind: string
{
    case Acquisition = 'acquisition';
    case Feature = 'feature';

    public function toInsightKind(): InsightKind
    {
        return InsightKind::from($this->value);
    }
}
