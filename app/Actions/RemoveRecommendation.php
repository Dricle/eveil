<?php

namespace App\Actions;

use App\Enums\InsightKind;
use App\Models\Project;

/**
 * Takes one recommendation off for good, unlike archiving it
 * (`UpdateRecommendation` with `status: archived`), which keeps it on
 * record as decided so no later analysis proposes it again.
 */
class RemoveRecommendation
{
    public function handle(Project $project, string $key): bool
    {
        return $project->insights()->whereIn('kind', InsightKind::recommendations())->where('source_ref', $key)->delete() > 0;
    }
}
