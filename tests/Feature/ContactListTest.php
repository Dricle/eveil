<?php

use App\Enums\ContactSearchStatus;
use App\Enums\EmailSource;
use App\Enums\EmailStatus;
use App\Jobs\FindCompanyContacts;
use App\Models\Company;
use App\Models\Lead;
use App\Models\Organization;
use App\Models\Project;
use App\Models\User;
use Illuminate\Support\Facades\Queue;

/**
 * @return array{0: User, 1: Project}
 */
function contacter(): array
{
    $user = User::factory()->create();
    Organization::factory()->create()->users()->attach($user, ['role' => 'owner']);

    return [$user, Project::factory()->for($user->organizations()->sole())->create()];
}

function contact(Project $project, Company $company, EmailStatus $status, string $email): Lead
{
    return Lead::factory()->create([
        'project_id' => $project->id,
        'company_id' => $company->id,
        'email' => $email,
        'email_status' => $status,
        'email_source' => EmailSource::Scraped,
    ]);
}

it('lists contacts, the ones worth sending to first', function () {
    [$user, $project] = contacter();
    $company = Company::factory()->create(['project_id' => $project->id, 'name' => 'Friterie du Centre']);

    contact($project, $company, EmailStatus::Risky, 'catchall@friterie.be');
    contact($project, $company, EmailStatus::Valid, 'marcel@friterie.be');

    $this->actingAs($user)->get(route('contacts.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('leads/Contacts')
            ->where('contacts.data.0.email', 'marcel@friterie.be')
            ->where('contacts.data.0.email_status', 'valid')
            ->where('contacts.data.0.email_source', 'scraped')
            ->where('contacts.data.0.company.name', 'Friterie du Centre')
            ->where('contacts.data.1.email_status', 'risky'));
});

it('keeps invalid addresses out of the list unless they are asked for', function () {
    [$user, $project] = contacter();
    $company = Company::factory()->create(['project_id' => $project->id]);

    contact($project, $company, EmailStatus::Invalid, 'rejete@friterie.be');
    contact($project, $company, EmailStatus::Valid, 'marcel@friterie.be');

    $this->actingAs($user)->get(route('contacts.index'))
        ->assertInertia(fn ($page) => $page->count('contacts.data', 1));

    $this->actingAs($user)->get(route('contacts.index', ['email_status' => 'invalid']))
        ->assertInertia(fn ($page) => $page
            ->count('contacts.data', 1)
            ->where('contacts.data.0.email', 'rejete@friterie.be'));
});

it('never shows an erased person, or one from another project', function () {
    [$user, $project] = contacter();
    $company = Company::factory()->create(['project_id' => $project->id]);

    contact($project, $company, EmailStatus::Valid, 'efface@friterie.be')->erase();
    Lead::factory()->create(['email_status' => EmailStatus::Valid]);

    $this->actingAs($user)->get(route('contacts.index'))
        ->assertInertia(fn ($page) => $page->count('contacts.data', 0));
});

it('filters to one company', function () {
    [$user, $project] = contacter();

    $wanted = Company::factory()->create(['project_id' => $project->id]);
    $other = Company::factory()->create(['project_id' => $project->id]);

    contact($project, $wanted, EmailStatus::Valid, 'marcel@voulu.be');
    contact($project, $other, EmailStatus::Valid, 'autre@autre.be');

    $this->actingAs($user)->get(route('contacts.index', ['company' => $wanted->id]))
        ->assertInertia(fn ($page) => $page
            ->count('contacts.data', 1)
            ->where('contacts.data.0.email', 'marcel@voulu.be'));
});

it('queues a search for one company and marks the row while it waits', function () {
    Queue::fake();

    [$user, $project] = contacter();
    $company = Company::factory()->create(['project_id' => $project->id]);

    $this->actingAs($user)
        ->post(route('contacts.search'), ['company' => $company->id])
        ->assertRedirect();

    expect($company->refresh()->contacts_status)->toBe(ContactSearchStatus::Queued);

    Queue::assertPushed(FindCompanyContacts::class, fn (FindCompanyContacts $job): bool => $job->company->is($company));
});

it('searches every kept company nobody has looked at yet', function () {
    Queue::fake();

    [$user, $project] = contacter();

    Company::factory()->create(['project_id' => $project->id]);
    Company::factory()->create(['project_id' => $project->id, 'rejected_at' => now()]);
    Company::factory()->create(['project_id' => $project->id, 'contacts_status' => ContactSearchStatus::Done]);

    $this->actingAs($user)->post(route('contacts.search'))->assertRedirect();

    // Not the rejected one, and not one already looked at: asking again is a
    // deliberate click on that row.
    Queue::assertPushed(FindCompanyContacts::class, 1);
});

it('refuses to search a company from another project', function () {
    Queue::fake();

    [$user] = contacter();
    $other = Company::factory()->create();

    $this->actingAs($user)
        ->post(route('contacts.search'), ['company' => $other->id])
        ->assertNotFound();

    Queue::assertNothingPushed();
});

it('records that a search failed, so the row says so instead of looking untouched', function () {
    [, $project] = contacter();
    $company = Company::factory()->create(['project_id' => $project->id, 'contacts_status' => ContactSearchStatus::Queued]);

    (new FindCompanyContacts($company))->failed(new RuntimeException('the site refused to be read'));

    expect($company->refresh()->contacts_status)->toBe(ContactSearchStatus::Failed)
        ->and($company->contacts_searched_at)->not->toBeNull();
});

it('counts the contacts found on each company', function () {
    [$user, $project] = contacter();
    $company = Company::factory()->create(['project_id' => $project->id]);

    contact($project, $company, EmailStatus::Valid, 'marcel@friterie.be');

    $this->actingAs($user)->get(route('companies.index'))
        ->assertInertia(fn ($page) => $page
            ->where('companies.data.0.contacts_count', 1)
            ->where('unsearched', 1));
});

it('searches people by name, role, address or company', function () {
    [$user, $project] = contacter();

    $company = Company::factory()->create(['project_id' => $project->id, 'name' => 'Atelier Dubois']);

    Lead::factory()->create(['project_id' => $project->id, 'company_id' => $company->id, 'first_name' => 'Sofia', 'last_name' => 'Renard', 'email' => 'sofia@dubois.be']);
    Lead::factory()->create(['project_id' => $project->id, 'first_name' => 'Tom', 'last_name' => 'Peeters', 'email' => 'tom@verlinden.nl']);

    $this->actingAs($user)->get(route('contacts.index', ['search' => 'dubois']))
        ->assertInertia(fn ($page) => $page
            ->has('contacts.data', 1)
            ->where('contacts.data.0.name', 'Sofia Renard'));
});

it('filters on the company column through the relation', function () {
    [$user, $project] = contacter();

    $company = Company::factory()->create(['project_id' => $project->id, 'name' => 'Atelier Dubois']);

    Lead::factory()->create(['project_id' => $project->id, 'company_id' => $company->id, 'first_name' => 'Sofia']);
    Lead::factory()->create(['project_id' => $project->id, 'first_name' => 'Tom']);

    // The company lives on another table, which is why the allowed filters are
    // named on the model rather than taken from the request.
    $this->actingAs($user)->get(route('contacts.index', ['filter' => ['company' => 'atelier']]))
        ->assertInertia(fn ($page) => $page->has('contacts.data', 1));
});

it('sorts people by name across the two columns it lives in', function () {
    [$user, $project] = contacter();

    Lead::factory()->create(['project_id' => $project->id, 'first_name' => 'Sofia', 'last_name' => 'Renard']);
    Lead::factory()->create(['project_id' => $project->id, 'first_name' => 'Tom', 'last_name' => 'Aerts']);

    $this->actingAs($user)->get(route('contacts.index', ['sort' => 'name', 'direction' => 'asc']))
        ->assertInertia(fn ($page) => $page->where('contacts.data.0.name', 'Tom Aerts'));
});
