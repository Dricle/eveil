<?php

namespace App\Ai\Tools;

use App\Models\Company;
use App\Models\Project;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Stringable;

/**
 * Logs a note on a company's own timeline. The account-level equivalent of
 * `AddLeadNote` - see there for why this needs no approval.
 */
class AddCompanyNote implements Tool
{
    public function __construct(private Project $project) {}

    public function description(): Stringable|string
    {
        return 'Adds a note to a company\'s own timeline. The company_id comes from ListCompanies or GetCompany.';
    }

    public function handle(Request $request): Stringable|string
    {
        $company = Company::query()
            ->where('project_id', $this->project->id)
            ->find($request->integer('company_id'));

        if ($company === null) {
            return 'No company with that id exists on this project. Call ListCompanies first.';
        }

        $note = $company->notes()->create([
            'body' => $request->string('body')->value(),
        ]);

        return "Note {$note->id} added.";
    }

    /**
     * @return array<string, mixed>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'company_id' => $schema->integer()
                ->description('The company to log this against, from ListCompanies or GetCompany.')
                ->required(),

            'body' => $schema->string()
                ->description('The note itself, written the way a person would jot it down.')
                ->required(),
        ];
    }
}
