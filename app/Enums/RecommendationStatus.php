<?php

namespace App\Enums;

/**
 * Where one acquisition idea (`projects.knowledge_base.recommendations`)
 * stands. `Proposed` is the only state a re-analysis may write: once the user
 * has decided, `Done` or `Archived` sticks, and `Archived` never comes back
 * (ADR-032).
 */
enum RecommendationStatus: string
{
    case Proposed = 'proposed';
    case Done = 'done';
    case Archived = 'archived';
}
