<?php

use App\Enums\OrganizationRole;
use App\Models\Organization;
use App\Models\Project;
use App\Models\User;

function organizationMember(Organization $organization, OrganizationRole $role): User
{
    $user = User::factory()->create();
    $organization->users()->attach($user, ['role' => $role->value]);

    return $user;
}

it('404s a member with no grant on the project directly', function () {
    $organization = Organization::factory()->create();
    $project = Project::factory()->for($organization)->create();
    $member = organizationMember($organization, OrganizationRole::Member);

    $this->actingAs($member)
        ->put(route('current-project.update', $project))
        ->assertNotFound();
});

it('allows a member once granted, and hides it again once revoked', function () {
    $organization = Organization::factory()->create();
    $project = Project::factory()->for($organization)->create();
    $member = organizationMember($organization, OrganizationRole::Member);

    $member->projects()->attach($project);
    expect(Project::query()->visibleTo($member)->whereKey($project->id)->exists())->toBeTrue();

    $member->projects()->detach($project);
    expect(Project::query()->visibleTo($member)->whereKey($project->id)->exists())->toBeFalse();
});

it('never restricts owner or admin, grant or not', function () {
    $organization = Organization::factory()->create();
    $project = Project::factory()->for($organization)->create();
    $owner = organizationMember($organization, OrganizationRole::Owner);
    $admin = organizationMember($organization, OrganizationRole::Admin);

    expect(Project::query()->visibleTo($owner)->whereKey($project->id)->exists())->toBeTrue()
        ->and(Project::query()->visibleTo($admin)->whereKey($project->id)->exists())->toBeTrue();
});

it('never lists a project from another organization', function () {
    $organization = Organization::factory()->create();
    $other = Organization::factory()->create();
    Project::factory()->for($other)->create();
    $owner = organizationMember($organization, OrganizationRole::Owner);

    expect(Project::query()->visibleTo($owner)->count())->toBe(0);
});
