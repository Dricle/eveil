<?php

namespace App\Actions;

use App\Models\Project;

/**
 * Takes one idea off the project's recommendations for good, unlike
 * archiving it (`UpdateRecommendation` with `status: archived`), which keeps
 * it on record as decided. The third of the writers of that JSON list, with
 * `AddRecommendation`.
 */
class RemoveRecommendation
{
    public function handle(Project $project, string $key): bool
    {
        $recommendations = $project->recommendations();
        $kept = array_values(array_filter($recommendations, fn (array $recommendation): bool => $recommendation['key'] !== $key));

        if (count($kept) === count($recommendations)) {
            return false;
        }

        $project->update([
            'knowledge_base' => [...$project->knowledge_base ?? [], 'recommendations' => $kept],
        ]);

        return true;
    }
}
