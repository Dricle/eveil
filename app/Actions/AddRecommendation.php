<?php

namespace App\Actions;

use App\Enums\InsightKind;
use App\Enums\InsightStatus;
use App\Enums\RecommendationKind;
use App\Models\Project;
use Illuminate\Support\Str;

/**
 * Adds one acquisition or feature idea to the project's recommendations,
 * proposed: from Evie (`App\Ai\Tools\ProposeRecommendation`) or the API.
 * `UpdateRecommendation`'s sibling. The key is the idea slugged, made
 * unique, and is what every later update names it by.
 */
class AddRecommendation
{
    /**
     * @param  array{idea: string, evidence: string, impact: string, effort: string, kind?: string|null}  $fields
     * @return string The new idea's key.
     */
    public function handle(Project $project, array $fields): string
    {
        $existingKeys = $project->insights()->whereIn('kind', InsightKind::recommendations())->pluck('source_ref')->all();
        $base = Str::slug($fields['idea'], '_') ?: 'idea';
        $key = $base;

        for ($suffix = 2; in_array($key, $existingKeys, true); $suffix++) {
            $key = "{$base}_{$suffix}";
        }

        $kind = RecommendationKind::tryFrom((string) ($fields['kind'] ?? '')) ?? RecommendationKind::Acquisition;

        $project->insights()->create([
            'kind' => $kind->toInsightKind(),
            'source' => 'evie',
            'source_ref' => $key,
            'title' => $fields['idea'],
            'angle' => $fields['evidence'],
            'details' => ['impact' => $fields['impact'], 'effort' => $fields['effort']],
            'status' => InsightStatus::Open,
        ]);

        return $key;
    }
}
