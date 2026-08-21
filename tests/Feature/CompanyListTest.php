<?php

use App\Enums\ContactSearchStatus;
use App\Enums\DiscoveryRunStatus;
use App\Enums\OutreachStatus;
use App\Models\Company;
use App\Models\CompanyTargetEvaluation;
use App\Models\DiscoveryRun;
use App\Models\Lead;
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

it('hides a company set aside until asked for it, and keeps the row', function () {
    [$user, $project] = lister();

    $company = scored($project, 'concurrent.be', 88);

    $this->actingAs($user)
        ->put(route('companies.status', $company), ['status' => 'client'])
        ->assertRedirect();

    expect($company->refresh()->status)->toBe(OutreachStatus::Client);

    $this->actingAs($user)->get(route('companies.index'))
        ->assertInertia(fn ($page) => $page->count('companies.data', 0)->where('total', 0));

    $this->actingAs($user)->get(route('companies.index', ['excluded' => 1]))
        ->assertInertia(fn ($page) => $page
            ->count('companies.data', 1)
            ->where('companies.data.0.status', 'client')
            ->where('companies.data.0.excluded', true));

    $this->actingAs($user)
        ->put(route('companies.status', $company), ['status' => 'new'])
        ->assertRedirect();

    expect($company->refresh()->status)->toBe(OutreachStatus::New);
});

it('refuses a status that is not one of ours', function () {
    [$user, $project] = lister();

    $company = scored($project, 'concurrent.be', 88);

    $this->actingAs($user)
        ->put(route('companies.status', $company), ['status' => 'maybe'])
        ->assertSessionHasErrors('status');

    expect($company->refresh()->status)->toBe(OutreachStatus::New);
});

it('never shows or restatuses a company from another project', function () {
    [$user] = lister();

    $other = Company::factory()->create();

    $this->actingAs($user)->get(route('companies.index'))
        ->assertInertia(fn ($page) => $page->count('companies.data', 0));

    $this->actingAs($user)
        ->put(route('companies.status', $other), ['status' => 'rejected'])
        ->assertNotFound();

    expect($other->refresh()->status)->toBe(OutreachStatus::New);
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

it('searches one box across the columns a person would type into', function () {
    [$user, $project] = lister();

    Company::factory()->create(['project_id' => $project->id, 'name' => 'Atelier Dubois', 'domain' => 'dubois.be', 'location' => 'Namur']);
    Company::factory()->create(['project_id' => $project->id, 'name' => 'Verlinden', 'domain' => 'verlinden.nl', 'location' => 'Utrecht']);

    // The name they remember, the domain they half-remember, or the town.
    $this->actingAs($user)->get(route('companies.index', ['search' => 'namur']))
        ->assertInertia(fn ($page) => $page
            ->has('companies.data', 1)
            ->where('companies.data.0.name', 'Atelier Dubois'));
});

it('narrows on one column at a time', function () {
    [$user, $project] = lister();

    Company::factory()->create(['project_id' => $project->id, 'name' => 'Dubois', 'industry' => 'Carpentry']);
    Company::factory()->create(['project_id' => $project->id, 'name' => 'Verlinden', 'industry' => 'Logistics']);

    $this->actingAs($user)->get(route('companies.index', ['filter' => ['industry' => 'carp']]))
        ->assertInertia(fn ($page) => $page
            ->has('companies.data', 1)
            ->where('companies.data.0.name', 'Dubois')
            // Echoed back so the box still holds what was typed after the visit.
            ->where('filters.filter.industry', 'carp'));
});

it('sorts on the column that was clicked', function () {
    [$user, $project] = lister();

    scored($project, 'zeta.be', 90);
    scored($project, 'alpha.be', 20);

    // Sorting is the database's job: the list is paginated, so a column sorted
    // in the browser would only sort the page on screen.
    $this->actingAs($user)->get(route('companies.index', ['sort' => 'name', 'direction' => 'asc']))
        ->assertInertia(fn ($page) => $page->where('companies.data.0.domain', 'alpha.be'));

    $this->actingAs($user)->get(route('companies.index', ['sort' => 'name', 'direction' => 'desc']))
        ->assertInertia(fn ($page) => $page->where('companies.data.0.domain', 'zeta.be'));
});

it('ignores a sort column nobody put on the screen', function () {
    [$user, $project] = lister();

    scored($project, 'moyen.be', 55);
    scored($project, 'excellent.be', 92);

    // The column name arrives in a query string and `orderBy` interpolates it,
    // so anything outside the whitelist falls back to the default order.
    $this->actingAs($user)->get(route('companies.index', ['sort' => 'name); drop table companies--']))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->where('companies.data.0.domain', 'excellent.be'));
});

it('carries a company verdict down to every person at it', function () {
    [$user, $project] = lister();

    $company = scored($project, 'client-existant.be', 88);
    $lead = Lead::factory()->create(['project_id' => $project->id, 'company_id' => $company->id]);
    $erased = Lead::factory()->create([
        'project_id' => $project->id,
        'company_id' => $company->id,
        'erased_at' => now(),
        'status' => OutreachStatus::Suppressed,
    ]);
    $elsewhere = Lead::factory()->create(['project_id' => $project->id]);

    $this->actingAs($user)
        ->put(route('companies.status', $company), ['status' => 'client'])
        ->assertRedirect();

    // A company already served says the same thing about every address at it.
    // Otherwise the contacts still read `new` and the cold pitch goes out.
    expect($lead->refresh()->status)->toBe(OutreachStatus::Client)
        // An erasure outlives any later verdict on the company.
        ->and($erased->refresh()->status)->toBe(OutreachStatus::Suppressed)
        ->and($elsewhere->refresh()->status)->toBe(OutreachStatus::New);
});

it('says the app is still looking while a run or a contact search is out', function () {
    [$user, $project] = lister();

    $company = scored($project, 'friterie.be', 80);

    $this->actingAs($user)->get(route('companies.index'))
        ->assertInertia(fn ($page) => $page->where('activity.searching', false));

    $profile = TargetProfile::factory()->create(['project_id' => $project->id]);
    $run = DiscoveryRun::factory()->create([
        'project_id' => $project->id,
        'target_profile_id' => $profile->id,
        'status' => DiscoveryRunStatus::Running,
        'candidates_found' => 12,
        'qualified_count' => 3,
    ]);

    // A list still filling up must not read as an empty market: "your market is
    // small" and "wait thirty seconds" look identical otherwise.
    $this->actingAs($user)->get(route('companies.index'))
        ->assertInertia(fn ($page) => $page
            ->where('activity.searching', true)
            ->where('activity.runs', 1)
            ->where('activity.candidates', 12)
            ->where('activity.qualified', 3));

    $run->update(['status' => DiscoveryRunStatus::Succeeded]);

    // A finished run stops the spinner, and a queued contact search starts it
    // again on its own account.
    $this->actingAs($user)->get(route('contacts.index'))
        ->assertInertia(fn ($page) => $page->where('activity.searching', false));

    $company->update(['contacts_status' => ContactSearchStatus::Queued]);

    $this->actingAs($user)->get(route('contacts.index'))
        ->assertInertia(fn ($page) => $page
            ->where('activity.searching', true)
            ->where('activity.contact_searches', 1)
            ->where('activity.runs', 0));
});

it('shows one company with everything found about it, and the people at it', function () {
    [$user, $project] = lister();

    $company = scored($project, 'friterie-centre.be', 88);
    $company->update([
        'industry' => 'Friterie',
        'location' => 'Namur',
        'facts' => ['phone' => '081 22 33 44'],
        'contacts_status' => ContactSearchStatus::Queued,
    ]);

    Lead::factory()->create([
        'project_id' => $project->id,
        'company_id' => $company->id,
        'first_name' => 'Marcel',
        'email' => 'marcel@friterie-centre.be',
    ]);

    $this->actingAs($user)->get(route('companies.show', $company))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('leads/Company')
            ->where('company.domain', 'friterie-centre.be')
            ->where('company.industry', 'Friterie')
            ->where('company.facts.phone', '081 22 33 44')
            // The sentence behind the score, which is also the opening line of
            // the first mail.
            ->has('company.evaluations', 1)
            ->has('company.contacts', 1)
            ->where('company.contacts.0.email', 'marcel@friterie-centre.be')
            // Still reading the site, so an empty list would say something
            // false about the company.
            ->where('company.searching', true));
});

it('never opens a company from another project', function () {
    [$user] = lister();

    $this->actingAs($user)->get(route('companies.show', Company::factory()->create()))->assertNotFound();
});

it('carries the approval state and the queue of what is still undecided', function () {
    [$user, $project] = lister();

    Company::factory()->create(['project_id' => $project->id, 'approved_at' => now()]);
    Company::factory()->count(2)->create(['project_id' => $project->id]);

    $this->actingAs($user)
        ->withSession(['current_project_id' => $project->id])
        ->get(route('companies.index', ['unapproved' => 1]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            // The only number that says whether the user has work to do here:
            // under anything but full autonomy nothing moves until these are
            // decided.
            ->where('unapproved', 2)
            ->has('companies.data', 2)
            ->where('companies.data.0.approved', false));
});
