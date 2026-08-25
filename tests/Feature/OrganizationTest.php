<?php

use App\Enums\OrganizationRole;
use App\Models\Organization;
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
