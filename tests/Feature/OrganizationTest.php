<?php

use App\Enums\OrganizationRole;
use App\Models\Organization;
use App\Models\Project;
use App\Models\User;

it('creates an organization with the creator as owner', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->post(route('organizations.store'), ['name' => 'Second Co'])
        ->assertSessionHasNoErrors();

    $organization = Organization::query()->where('name', 'Second Co')->sole();

    expect($organization->roleOf($user))->toBe(OrganizationRole::Owner);
});

it('seeds a trial balance on the cloud edition', function () {
    config()->set('eveil.edition', 'cloud');
    $user = User::factory()->create();

    $this->actingAs($user)->post(route('organizations.store'), ['name' => 'Cloud Co']);

    $organization = Organization::query()->where('name', 'Cloud Co')->sole();

    expect($organization->credits_balance)->toBe(5000)
        ->and($organization->isOnTrial())->toBeTrue();
});

it('grants no credits at all on self-hosted', function () {
    config()->set('eveil.edition', 'self');
    $user = User::factory()->create();

    $this->actingAs($user)->post(route('organizations.store'), ['name' => 'Self Co']);

    $organization = Organization::query()->where('name', 'Self Co')->sole();

    expect($organization->credits_balance)->toBe(0);
});

it('redirects to create a project already scoped to the new organization', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->post(route('organizations.store'), ['name' => 'Third Co']);

    $organization = Organization::query()->where('name', 'Third Co')->sole();

    $response->assertRedirect(route('projects.create', ['organization_id' => $organization->id]));
});

it('lets an owner rename the current organization', function () {
    $organization = Organization::factory()->create(['name' => 'Old name']);
    $user = User::factory()->create();
    $organization->users()->attach($user, ['role' => OrganizationRole::Owner->value]);
    Project::factory()->for($organization)->create();

    $this->actingAs($user)
        ->put(route('settings.organization.general.update'), ['name' => 'New name'])
        ->assertRedirect(route('settings.organization.general.edit'));

    expect($organization->fresh()->name)->toBe('New name');
});

it('shows the current organization on the General settings screen, to any member', function () {
    $organization = Organization::factory()->create(['name' => 'Acme']);
    $user = User::factory()->create();
    $organization->users()->attach($user, ['role' => OrganizationRole::Member->value]);
    $project = Project::factory()->for($organization)->create();
    $user->projects()->attach($project);

    $this->actingAs($user)->get(route('settings.organization.general.edit'))
        ->assertInertia(fn ($page) => $page
            ->component('settings/OrganizationGeneral')
            ->where('organization.name', 'Acme'));
});

it('refuses a plain member renaming the organization', function () {
    $organization = Organization::factory()->create(['name' => 'Old name']);
    $user = User::factory()->create();
    $organization->users()->attach($user, ['role' => OrganizationRole::Member->value]);
    $project = Project::factory()->for($organization)->create();
    $user->projects()->attach($project);

    $this->actingAs($user)
        ->put(route('settings.organization.general.update'), ['name' => 'New name'])
        ->assertForbidden();

    expect($organization->fresh()->name)->toBe('Old name');
});
