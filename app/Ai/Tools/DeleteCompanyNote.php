<?php

namespace App\Ai\Tools;

use App\Models\Company;
use App\Models\Project;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Stringable;

/**
 * Removes one note from a company's own timeline. The account-level
 * equivalent of `DeleteLeadNote`.
 */
class DeleteCompanyNote implements Tool
{
    public function __construct(private Project $project) {}

    public function description(): Stringable|string
    {
        return 'Deletes one note from a company\'s own timeline.';
    }

    public function handle(Request $request): Stringable|string
    {
        $company = Company::query()
            ->where('project_id', $this->project->id)
            ->find($request->integer('company_id'));

        if ($company === null) {
            return 'No company with that id exists on this project.';
        }

        $note = $company->notes()->find($request->integer('note_id'));

        if ($note === null) {
            return 'No note with that id exists on this company. Call GetCompany first.';
        }

        $note->delete();

        return 'Note deleted.';
    }

    /**
     * @return array<string, mixed>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'company_id' => $schema->integer()
                ->description('The company the note belongs to.')
                ->required(),

            'note_id' => $schema->integer()
                ->description('The note to delete, from GetCompany.')
                ->required(),
        ];
    }
}
