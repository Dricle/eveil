<?php

namespace App\Ai\Tools;

use App\Actions\DeleteAllCompanies as DeleteAllCompaniesAction;
use App\Models\Project;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Concerns\InteractsWithApprovals;
use Laravel\Ai\Contracts\Approvable;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Stringable;

/**
 * Deletes every company found for the project, in one call - not one tool
 * call per company. Gated behind approval like DeleteTargetProfile: this
 * cascades to every company's notes and fit scores and cannot be undone.
 */
class DeleteAllCompanies implements Approvable, Tool
{
    use InteractsWithApprovals;

    public function __construct(private Project $project)
    {
        $this->requireApproval('This permanently deletes every company found for this project.');
    }

    public function description(): Stringable|string
    {
        return 'Permanently deletes every company found for this project, along with their notes and fit scores. Leads are untouched: use DeleteAllLeads separately for those. Cannot be undone.';
    }

    public function handle(Request $request): Stringable|string
    {
        $count = app(DeleteAllCompaniesAction::class)->handle($this->project);

        return "Deleted {$count} ".str('company')->plural($count).'.';
    }

    /**
     * @return array<string, mixed>
     */
    public function schema(JsonSchema $schema): array
    {
        return [];
    }
}
