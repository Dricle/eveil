<?php

namespace App\Enums;

/**
 * What one idea in `projects.knowledge_base.recommendations` is about. Both
 * kinds share the same proposed/done/archived lifecycle (ADR-032), only the
 * agent that proposes them differs: `Acquisition` is a lever the Website
 * agent found missing from the site, `Feature` a capability competitors
 * offer that the product does not (`App\Ai\Agents\CompetitorAnalyst`).
 */
enum RecommendationKind: string
{
    case Acquisition = 'acquisition';
    case Feature = 'feature';
}
