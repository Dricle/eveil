<?php

namespace App\Actions;

use App\Models\Lead;
use App\Models\Project;

/**
 * A full reset, not `Lead::erase()`. Erasure keeps the row (and its hash) so
 * the person is never re-discovered and re-contacted - the right call for a
 * "forget me" request, wrong here: this is the user asking to start a
 * project's discovery over, and finding the same people again on the next
 * run is exactly what they want.
 *
 * Cascades to `campaign_leads`, `lead_notes` and `messages`.
 */
class DeleteAllLeads
{
    public function handle(Project $project): int
    {
        return Lead::query()->where('project_id', $project->id)->delete();
    }
}
