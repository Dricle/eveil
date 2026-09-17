<?php

namespace App\Actions;

use App\Models\Company;
use App\Models\Project;

/**
 * A full reset, not the ordinary way a company leaves the pipeline.
 * Everywhere else a company is excluded by status (`OutreachStatus`), never
 * deleted, because deleting it only means the next discovery run finds it
 * again (see `CompanyController`). This action exists for the one case where
 * finding it again is exactly the point: the user asking to start a
 * project's discovery over from nothing.
 *
 * Cascades to `company_notes` and `company_target_evaluations`. Leaves
 * `leads.company_id` null rather than deleting the lead itself - that is
 * `DeleteAllLeads`'s job, kept separate so either can run without the other.
 */
class DeleteAllCompanies
{
    public function handle(Project $project): int
    {
        return Company::query()->where('project_id', $project->id)->delete();
    }
}
