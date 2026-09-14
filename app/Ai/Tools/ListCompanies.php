<?php

namespace App\Ai\Tools;

use App\Models\Company;
use App\Models\Project;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Stringable;

/**
 * Read-only: the leads already found for this project, optionally narrowed to
 * one target profile, so the model can answer "what did we find last week"
 * without dispatching anything.
 */
class ListCompanies implements Tool
{
    private const LIMIT = 20;

    public function __construct(private Project $project) {}

    public function description(): Stringable|string
    {
        return 'Lists companies already found for this project (contactable ones only, newest first, capped at '.self::LIMIT.'). Optionally filter by target_profile_id.';
    }

    public function handle(Request $request): Stringable|string
    {
        $companies = Company::query()
            ->where('project_id', $this->project->id)
            ->contactable()
            ->when(
                $request->integer('target_profile_id'),
                fn ($query, int $profileId) => $query->whereHas(
                    'evaluations',
                    fn ($evaluations) => $evaluations->where('target_profile_id', $profileId),
                ),
            )
            ->latest('discovered_at')
            ->limit(self::LIMIT)
            ->get(['id', 'name', 'domain', 'industry', 'status', 'approved_at'])
            ->map(fn (Company $company): array => [
                'id' => $company->id,
                'name' => $company->name,
                'domain' => $company->domain,
                'industry' => $company->industry,
                'status' => $company->status->value,
                'approved' => $company->approved_at !== null,
            ]);

        if ($companies->isEmpty()) {
            return 'No companies match.';
        }

        return (string) json_encode($companies->all());
    }

    /**
     * @return array<string, mixed>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'target_profile_id' => $schema->integer()
                ->description('Only companies evaluated against this target profile. Omit to list every contactable company.'),
        ];
    }
}
