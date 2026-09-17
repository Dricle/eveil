<?php

use App\Ai\Agents\CompanyQualifier;
use App\Models\Project;
use App\Models\TargetProfile;
use App\Services\Discovery\Candidate;
use Laravel\Ai\Prompts\AgentPrompt;

function competitorExclusionVerdict(): array
{
    return [
        'is_a_prospect' => true,
        'fit_score' => 80,
        'fit_reason' => 'Fits.',
        'company_name' => 'Acme',
        'industry' => 'Software',
        'size' => 'unknown',
        'location' => 'unknown',
        'language' => 'en',
    ];
}

function qualifyForProductContext(Project $project, TargetProfile $targetProfile): void
{
    $candidate = new Candidate(name: 'Acme', website: null, source: 'web_search', facts: ['name' => 'Acme']);

    (new CompanyQualifier($project, $targetProfile, $candidate, null))->qualify();
}

it('tells the qualifier what we sell, so it can recognise a competitor of it', function () {
    $project = Project::factory()->create(['knowledge_base' => [
        'what_it_does' => 'Eveil finds companies and people to sell to, and runs outreach for them.',
        'competitors' => ['lemlist', 'Instantly'],
    ]]);
    $targetProfile = TargetProfile::factory()->for($project)->create();

    CompanyQualifier::fake([competitorExclusionVerdict()]);

    qualifyForProductContext($project, $targetProfile);

    CompanyQualifier::assertPrompted(fn (AgentPrompt $prompt): bool => str_contains(
        (string) $prompt->prompt,
        'Eveil finds companies and people to sell to, and runs outreach for them.',
    ) && str_contains((string) $prompt->prompt, 'lemlist, Instantly'));
});

it('never mentions a product context when the project has not been analysed yet', function () {
    $project = Project::factory()->create(['knowledge_base' => null]);
    $targetProfile = TargetProfile::factory()->for($project)->create();

    CompanyQualifier::fake([competitorExclusionVerdict()]);

    qualifyForProductContext($project, $targetProfile);

    CompanyQualifier::assertPrompted(fn (AgentPrompt $prompt): bool => ! str_contains(
        (string) $prompt->prompt,
        'What we are selling',
    ));
});
