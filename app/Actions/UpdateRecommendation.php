<?php

namespace App\Actions;

use App\Models\Project;

/**
 * The sole writer of one acquisition idea's row, whatever changes: a status
 * decided by the user (`RecommendationStatusController`) or a rewrite Evie
 * makes on the user's behalf (`App\Ai\Tools\UpdateRecommendation`). Same
 * "one writer" shape as `SetOutreachStatus` - both callers hand it a key and
 * whichever fields changed, and neither reaches into the JSON column itself.
 */
class UpdateRecommendation
{
    /**
     * @param  array<string, mixed>  $fields
     */
    public function handle(Project $project, string $key, array $fields): bool
    {
        $found = false;

        $recommendations = array_map(function (array $recommendation) use ($key, $fields, &$found): array {
            if ($recommendation['key'] !== $key) {
                return $recommendation;
            }

            $found = true;

            return [...$recommendation, ...$fields];
        }, $project->recommendations());

        if (! $found) {
            return false;
        }

        $project->update([
            'knowledge_base' => [...$project->knowledge_base ?? [], 'recommendations' => $recommendations],
        ]);

        return true;
    }
}
