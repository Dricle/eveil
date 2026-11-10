<?php

namespace App\Actions;

use App\Enums\InsightKind;
use App\Enums\InsightStatus;
use App\Models\Insight;

/**
 * One Insights item marked read or unread, used or dismissed: from the
 * Insights page, the API, or Evie (`App\Ai\Tools\UpdateInsight`). Deciding
 * on an item means it was read, and a missing feature marked done joins the
 * key features of the knowledge base (`Project::withBuiltFeatures()`).
 */
class UpdateInsight
{
    public function handle(Insight $idea, ?bool $read, ?InsightStatus $status): void
    {
        $changes = [];

        if ($read !== null) {
            $changes['read_at'] = $read ? ($idea->read_at ?? now()) : null;
        }

        if ($status !== null && $status !== InsightStatus::Open) {
            $changes['status'] = $status;
            $changes['read_at'] = $idea->read_at ?? now();
        }

        $idea->update($changes);

        if ($status === InsightStatus::Used && $idea->kind === InsightKind::Feature) {
            $idea->project->syncBuiltFeatures();
        }
    }
}
