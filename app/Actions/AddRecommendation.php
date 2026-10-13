<?php

namespace App\Actions;

use App\Enums\RecommendationKind;
use App\Enums\RecommendationStatus;
use App\Models\Project;
use Illuminate\Support\Str;

/**
 * Adds one acquisition or feature idea to the project's recommendations,
 * proposed: from Evie (`App\Ai\Tools\ProposeRecommendation`) or the API.
 * `UpdateRecommendation`'s sibling, so neither caller writes the JSON column
 * itself. The key is the idea slugged, made unique, and is what every later
 * update names it by.
 */
class AddRecommendation
{
    /**
     * @param  array{idea: string, evidence: string, impact: string, effort: string, kind?: string|null}  $fields
     * @return string The new idea's key.
     */
    public function handle(Project $project, array $fields): string
    {
        $existingKeys = collect($project->recommendations())->pluck('key')->all();
        $base = Str::slug($fields['idea'], '_') ?: 'idea';
        $key = $base;

        for ($suffix = 2; in_array($key, $existingKeys, true); $suffix++) {
            $key = "{$base}_{$suffix}";
        }

        $project->update([
            'knowledge_base' => [...$project->knowledge_base ?? [], 'recommendations' => [
                ...$project->recommendations(),
                [
                    'key' => $key,
                    'idea' => $fields['idea'],
                    'evidence' => $fields['evidence'],
                    'impact' => $fields['impact'],
                    'effort' => $fields['effort'],
                    'status' => RecommendationStatus::Proposed->value,
                    'kind' => $fields['kind'] ?? RecommendationKind::Acquisition->value,
                ],
            ]],
        ]);

        return $key;
    }
}
