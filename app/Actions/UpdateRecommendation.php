<?php

namespace App\Actions;

use App\Enums\InsightKind;
use App\Enums\RecommendationStatus;
use App\Models\Insight;
use App\Models\Project;

/**
 * The sole writer of one recommendation, whatever changes: a status decided
 * by the user (`RecommendationStatusController`) or a rewrite Evie makes on
 * the user's behalf (`App\Ai\Tools\UpdateRecommendation`). Same "one writer"
 * shape as `SetOutreachStatus`: both callers hand it a key and whichever
 * fields changed, in the recommendation vocabulary, and this translates
 * them onto the `insights` row. A feature marked done joins the key
 * features (`Project::syncBuiltFeatures()`).
 */
class UpdateRecommendation
{
    /**
     * @param  array<string, mixed>  $fields
     */
    public function handle(Project $project, string $key, array $fields): bool
    {
        /** @var Insight|null $idea */
        $idea = $project->insights()->whereIn('kind', InsightKind::recommendations())->where('source_ref', $key)->first();

        if ($idea === null) {
            return false;
        }

        $details = $idea->details ?? [];

        foreach (['impact', 'effort'] as $field) {
            if (isset($fields[$field])) {
                $details[$field] = (string) $fields[$field];
            }
        }

        $status = RecommendationStatus::tryFrom((string) ($fields['status'] ?? ''));

        $idea->update(array_filter([
            'title' => isset($fields['idea']) ? (string) $fields['idea'] : null,
            'angle' => isset($fields['evidence']) ? (string) $fields['evidence'] : null,
            'details' => $details,
            'status' => $status?->toInsightStatus(),
            // Deciding on one means it was read.
            'read_at' => $status !== null && $status !== RecommendationStatus::Proposed ? ($idea->read_at ?? now()) : null,
        ], fn (mixed $value): bool => $value !== null));

        if ($status === RecommendationStatus::Done && $idea->kind === InsightKind::Feature) {
            $project->syncBuiltFeatures();
        }

        return true;
    }
}
