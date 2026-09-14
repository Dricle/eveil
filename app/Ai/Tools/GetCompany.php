<?php

namespace App\Ai\Tools;

use App\Models\Company;
use App\Models\CompanyNote;
use App\Models\CompanyTargetEvaluation;
use App\Models\Lead;
use App\Models\Project;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Stringable;

/**
 * Read-only: one company's full detail - why it was kept, the people found
 * there (with their ids, for GetContact and the lead-note tools), and this
 * company's own timeline. What ListCompanies only gives the shape of.
 */
class GetCompany implements Tool
{
    public function __construct(private Project $project) {}

    public function description(): Stringable|string
    {
        return 'Reads one company\'s full detail: firmographics, why it was kept (per target profile), the people found there (with their ids), and this company\'s own timeline of notes.';
    }

    public function handle(Request $request): Stringable|string
    {
        $company = Company::query()
            ->where('project_id', $this->project->id)
            ->with(['evaluations.targetProfile', 'leads', 'notes.user'])
            ->find($request->integer('company_id'));

        if ($company === null) {
            return 'No company with that id exists on this project. Call ListCompanies first.';
        }

        return (string) json_encode([
            'id' => $company->id,
            'name' => $company->name,
            'domain' => $company->domain,
            'industry' => $company->industry,
            'size' => $company->size,
            'location' => $company->location,
            'status' => $company->status->value,
            'approved' => $company->approved_at !== null,
            'evaluations' => $company->evaluations->map(fn (CompanyTargetEvaluation $evaluation): array => [
                'profile' => $evaluation->targetProfile?->name,
                'fit_score' => $evaluation->fit_score,
                'fit_reason' => $evaluation->fit_reason,
            ])->all(),
            'contacts' => $company->leads->reject(fn (Lead $lead): bool => $lead->isErased())
                ->map(fn (Lead $lead): array => [
                    'id' => $lead->id,
                    'name' => mb_trim($lead->first_name.' '.$lead->last_name) ?: null,
                    'email' => $lead->email,
                    'title' => $lead->title,
                    'status' => $lead->status->value,
                ])->values()->all(),
            'notes' => $company->notes->map(fn (CompanyNote $note): array => [
                'id' => $note->id,
                'body' => $note->body,
                'author' => $note->user?->name,
                'at' => $note->created_at?->toIso8601String(),
            ])->all(),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'company_id' => $schema->integer()
                ->description('The company to read, from ListCompanies.')
                ->required(),
        ];
    }
}
