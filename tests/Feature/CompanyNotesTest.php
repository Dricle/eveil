<?php

use App\Models\Company;
use App\Models\CompanyNote;
use App\Models\Organization;
use App\Models\Project;
use App\Models\User;

/**
 * @return array{0: User, 1: Project}
 */
function companyOwner(): array
{
    $user = User::factory()->create();
    Organization::factory()->create()->users()->attach($user, ['role' => 'owner']);

    return [$user, Project::factory()->for($user->organizations()->sole())->create()];
}

it('logs a note on a company\'s timeline, newest first', function () {
    [$user, $project] = companyOwner();
    $company = Company::factory()->create(['project_id' => $project->id]);

    $this->actingAs($user)
        ->post(route('companies.notes.store', $company), ['body' => 'Spoke to their ops manager, wants a demo.'])
        ->assertRedirect();

    $this->actingAs($user)
        ->post(route('companies.notes.store', $company), ['body' => 'Demo booked for next week.'])
        ->assertRedirect();

    $this->actingAs($user)->get(route('companies.show', $company))
        ->assertInertia(fn ($page) => $page
            ->has('company.notes', 2)
            ->where('company.notes.0.body', 'Demo booked for next week.')
            ->where('company.notes.0.author', $user->name)
            ->where('company.notes.1.body', 'Spoke to their ops manager, wants a demo.'));
});

it('requires a body', function () {
    [$user, $project] = companyOwner();
    $company = Company::factory()->create(['project_id' => $project->id]);

    $this->actingAs($user)
        ->post(route('companies.notes.store', $company), ['body' => ''])
        ->assertSessionHasErrors('body');
});

it('deletes a note', function () {
    [$user, $project] = companyOwner();
    $company = Company::factory()->create(['project_id' => $project->id]);
    $note = CompanyNote::factory()->create(['company_id' => $company->id]);

    $this->actingAs($user)
        ->delete(route('companies.notes.destroy', [$company, $note]))
        ->assertRedirect();

    expect(CompanyNote::query()->find($note->id))->toBeNull();
});

it('never lets one project write or delete another project\'s notes', function () {
    [$user] = companyOwner();
    $theirs = Company::factory()->create();
    $note = CompanyNote::factory()->create(['company_id' => $theirs->id]);

    $this->actingAs($user)
        ->post(route('companies.notes.store', $theirs), ['body' => 'Sneaky.'])
        ->assertNotFound();

    $this->actingAs($user)
        ->delete(route('companies.notes.destroy', [$theirs, $note]))
        ->assertNotFound();

    expect(CompanyNote::query()->find($note->id))->not->toBeNull();
});
