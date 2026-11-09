<?php

use App\Ai\Agents\CompanyQualifier;
use App\Models\DiscoveryRun;
use App\Models\Project;
use App\Models\TargetProfile;
use App\Services\Discovery\Candidate;
use App\Services\Discovery\JobBoards;
use App\Services\Discovery\Qualifier;
use Illuminate\Support\Facades\Http;
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

it('reads the open roles off the job board a company links to, and judges it with them', function () {
    $project = Project::factory()->create();
    $targetProfile = TargetProfile::factory()->for($project)->create();
    $run = DiscoveryRun::factory()->for($project)->create();

    Http::fake([
        '*/robots.txt' => Http::response('', 404),
        'boards-api.greenhouse.io/v1/boards/acme/jobs' => Http::response(['jobs' => [
            ['title' => 'Office Manager'], ['title' => 'First Account Executive'], ['title' => 'Office Manager'],
        ]]),
        'acme.test*' => Http::response(
            '<html><body><h1>Acme</h1><p>'.str_repeat('We build software for dentists. ', 20).'</p>'
            .'<a href="https://boards.greenhouse.io/acme">Careers</a></body></html>',
            200,
            ['Content-Type' => 'text/html'],
        ),
    ]);

    CompanyQualifier::fake([competitorExclusionVerdict()]);

    $company = app(Qualifier::class)->qualify(
        $targetProfile,
        $run,
        new Candidate(name: 'Acme', website: 'https://acme.test', source: 'web_search'),
    );

    expect($company->facts['open_roles'])->toBe(['Office Manager', 'First Account Executive']);

    CompanyQualifier::assertPrompted(fn (AgentPrompt $prompt): bool => str_contains((string) $prompt->prompt, 'First Account Executive'));
});

it('never guesses a job board the company does not link to', function () {
    Http::fake();

    expect(app(JobBoards::class)->openRoles(['https://acme.test/careers', 'https://boards.greenhouse.io/embed/job_board?for=x']))->toBe([]);

    Http::assertNothingSent();
});
