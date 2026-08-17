<?php

use App\Models\Company;
use App\Models\CompanyTargetEvaluation;
use App\Models\Organization;
use App\Models\Project;
use App\Models\TargetProfile;
use App\Models\User;

/**
 * @return array{0: User, 1: Project}
 */
function lister(): array
{
    $user = User::factory()->create();
    Organization::factory()->create()->users()->attach($user, ['role' => 'owner']);

    return [$user, Project::factory()->for($user->organizations()->sole())->create()];
}

function scored(Project $project, string $domain, int $score, ?TargetProfile $profile = null): Company
{
    $company = Company::factory()->create(['project_id' => $project->id, 'domain' => $domain, 'name' => $domain]);

    CompanyTargetEvaluation::factory()->create([
        'company_id' => $company->id,
        'target_profile_id' => ($profile ?? TargetProfile::factory()->create(['project_id' => $project->id]))->id,
        'fit_score' => $score,
        'fit_reason' => "Scored {$score} because it matches.",
    ]);

    return $company;
}

it('lists companies best fit first, with the sentence that justifies the score', function () {
    [$user, $project] = lister();

    scored($project, 'moyen.be', 55);
    scored($project, 'excellent.be', 92);

    $this->actingAs($user)->get(route('companies.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('leads/Companies')
            ->where('companies.data.0.domain', 'excellent.be')
            ->where('companies.data.0.fit_score', 92)
            ->where('companies.data.0.evaluations.0.fit_reason', 'Scored 92 because it matches.')
            ->where('companies.data.1.domain', 'moyen.be')
            ->where('total', 2));
});

it('filters by score and by profile', function () {
    [$user, $project] = lister();

    $wanted = TargetProfile::factory()->create(['project_id' => $project->id, 'name' => 'Friteries']);

    scored($project, 'faible.be', 30, $wanted);
    scored($project, 'fort.be', 90, $wanted);
    scored($project, 'autre-profil.be', 95);

    $this->actingAs($user)->get(route('companies.index', ['min_score' => 70, 'profile' => $wanted->id]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->count('companies.data', 1)
            ->where('companies.data.0.domain', 'fort.be'));
});

it('hides a rejected company until asked for it, and keeps the row', function () {
    [$user, $project] = lister();

    $company = scored($project, 'concurrent.be', 88);

    $this->actingAs($user)->post(route('companies.reject', $company))->assertRedirect();

    expect($company->refresh()->rejected_at)->not->toBeNull();

    $this->actingAs($user)->get(route('companies.index'))
        ->assertInertia(fn ($page) => $page->count('companies.data', 0)->where('total', 0));

    $this->actingAs($user)->get(route('companies.index', ['rejected' => 1]))
        ->assertInertia(fn ($page) => $page
            ->count('companies.data', 1)
            ->where('companies.data.0.rejected', true));

    $this->actingAs($user)->delete(route('companies.restore', $company))->assertRedirect();

    expect($company->refresh()->rejected_at)->toBeNull();
});

it('never shows or rejects a company from another project', function () {
    [$user] = lister();

    $other = Company::factory()->create();

    $this->actingAs($user)->get(route('companies.index'))
        ->assertInertia(fn ($page) => $page->count('companies.data', 0));

    $this->actingAs($user)->post(route('companies.reject', $other))->assertNotFound();

    expect($other->refresh()->rejected_at)->toBeNull();
});

it('sorts on the best profile rather than an average', function () {
    [$user, $project] = lister();

    // One profile hates it, another loves it: that is a good company, and the
    // score the same business gets under two profiles is never merged.
    $company = scored($project, 'polyvalent.be', 20);

    CompanyTargetEvaluation::factory()->create([
        'company_id' => $company->id,
        'target_profile_id' => TargetProfile::factory()->create(['project_id' => $project->id])->id,
        'fit_score' => 95,
        'fit_reason' => 'Exactly the second profile.',
    ]);

    scored($project, 'correct.be', 60);

    $this->actingAs($user)->get(route('companies.index'))
        ->assertInertia(fn ($page) => $page
            ->where('companies.data.0.domain', 'polyvalent.be')
            ->where('companies.data.0.fit_score', 95)
            ->count('companies.data.0.evaluations', 2));
});
