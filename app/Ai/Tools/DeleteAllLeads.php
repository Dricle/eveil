<?php

namespace App\Ai\Tools;

use App\Actions\DeleteAllLeads as DeleteAllLeadsAction;
use App\Models\Project;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Concerns\InteractsWithApprovals;
use Laravel\Ai\Contracts\Approvable;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Stringable;

/**
 * Deletes every lead found for the project, in one call - not one tool call
 * per lead. Gated behind approval like DeleteTargetProfile: this is a real
 * hard delete, not `Lead::erase()`, and cannot be undone.
 */
class DeleteAllLeads implements Approvable, Tool
{
    use InteractsWithApprovals;

    public function __construct(private Project $project)
    {
        $this->requireApproval('This permanently deletes every lead found for this project.');
    }

    public function description(): Stringable|string
    {
        return 'Permanently deletes every lead (contact) found for this project, along with their notes and message history. Companies are untouched: use DeleteAllCompanies separately for those. Cannot be undone.';
    }

    public function handle(Request $request): Stringable|string
    {
        $count = app(DeleteAllLeadsAction::class)->handle($this->project);

        return "Deleted {$count} ".str('lead')->plural($count).'.';
    }

    /**
     * @return array<string, mixed>
     */
    public function schema(JsonSchema $schema): array
    {
        return [];
    }
}
