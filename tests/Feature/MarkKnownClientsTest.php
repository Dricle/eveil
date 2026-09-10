<?php

use App\Enums\OutreachStatus;
use App\Models\Company;
use App\Models\Lead;
use App\Models\Organization;
use App\Models\Project;
use App\Models\User;

/**
 * @return array{0: User, 1: Project}
 */
function knownClientsSubmitter(): array
{
    $user = User::factory()->create();
    Organization::factory()->create()->users()->attach($user, ['role' => 'owner']);
    $project = Project::factory()->for($user->organizations()->sole())->create();

    return [$user, $project];
}

it('marks a pasted email as an existing client, not a fresh lead', function () {
    [$user] = knownClientsSubmitter();

    $this->actingAs($user)->post(route('companies.known-clients.store'), [
        'entries' => 'Jean@Example.com',
    ])->assertRedirect();

    $lead = Lead::sole();

    expect($lead->email)->toBe('jean@example.com')
        ->and($lead->status)->toBe(OutreachStatus::Client)
        ->and($lead->source)->toBe('manual');
});

it('marks a pasted website as an existing client and propagates to its known contacts', function () {
    [$user, $project] = knownClientsSubmitter();

    $company = Company::factory()->create(['project_id' => $project->id, 'domain' => 'acme.example']);
    $lead = Lead::factory()->create(['project_id' => $project->id, 'company_id' => $company->id]);

    $this->actingAs($user)->post(route('companies.known-clients.store'), [
        'entries' => 'https://www.acme.example/about',
    ])->assertRedirect();

    expect(Company::sole()->status)->toBe(OutreachStatus::Client)
        ->and($lead->fresh()->status)->toBe(OutreachStatus::Client);
});

it('accepts emails and websites mixed on their own lines and skips blanks and duplicates', function () {
    [$user] = knownClientsSubmitter();

    $this->actingAs($user)->post(route('companies.known-clients.store'), [
        'entries' => "jean@example.com\n\nhttps://acme.example\njean@example.com\n",
    ])->assertRedirect();

    expect(Lead::count())->toBe(1)
        ->and(Company::count())->toBe(1);
});

it('is safe to submit the same client twice', function () {
    [$user] = knownClientsSubmitter();

    $submit = fn () => $this->actingAs($user)->post(route('companies.known-clients.store'), [
        'entries' => "jean@example.com\nhttps://acme.example",
    ]);

    $submit()->assertRedirect();
    $submit()->assertRedirect();

    expect(Lead::count())->toBe(1)
        ->and(Company::count())->toBe(1)
        ->and(Lead::sole()->status)->toBe(OutreachStatus::Client)
        ->and(Company::sole()->status)->toBe(OutreachStatus::Client);
});
