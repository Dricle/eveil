<?php

use App\Enums\OrganizationRole;
use App\Models\Organization;
use App\Models\Project;
use App\Models\User;

function inOrg(Organization $organization, OrganizationRole $role): User
{
    $user = User::factory()->create();
    $organization->users()->attach($user, ['role' => $role->value]);

    return $user;
}

function orgWithProjectAndOwner(): array
{
    $organization = Organization::factory()->create();
    Project::factory()->for($organization)->create();
    $owner = inOrg($organization, OrganizationRole::Owner);

    return [$organization, $owner];
}

it('lets an owner change a member role', function () {
    [$organization, $owner] = orgWithProjectAndOwner();
    $member = inOrg($organization, OrganizationRole::Member);

    $this->actingAs($owner)
        ->put(route('settings.members.update', $member), ['role' => 'admin'])
        ->assertSessionHasNoErrors();

    expect($organization->roleOf($member))->toBe(OrganizationRole::Admin);
});

it('lets an owner remove a member', function () {
    [$organization, $owner] = orgWithProjectAndOwner();
    $member = inOrg($organization, OrganizationRole::Member);

    $this->actingAs($owner)
        ->delete(route('settings.members.destroy', $member))
        ->assertSessionHasNoErrors();

    expect($organization->roleOf($member))->toBeNull();
});

it('lets a member leave by themselves', function () {
    [$organization] = orgWithProjectAndOwner();
    $member = inOrg($organization, OrganizationRole::Member);
    $member->projects()->attach($organization->projects()->sole());

    $this->actingAs($member)
        ->delete(route('settings.members.destroy', $member))
        ->assertSessionHasNoErrors();

    expect($organization->roleOf($member))->toBeNull();
});

it('refuses a plain member the right to remove somebody else', function () {
    [$organization] = orgWithProjectAndOwner();
    $member = inOrg($organization, OrganizationRole::Member);
    $other = inOrg($organization, OrganizationRole::Member);
    $member->projects()->attach($organization->projects()->sole());

    $this->actingAs($member)
        ->delete(route('settings.members.destroy', $other))
        ->assertForbidden();

    expect($organization->roleOf($other))->toBe(OrganizationRole::Member);
});

it('refuses to remove the last owner', function () {
    [$organization, $owner] = orgWithProjectAndOwner();
    $admin = inOrg($organization, OrganizationRole::Admin);

    $this->actingAs($admin)
        ->delete(route('settings.members.destroy', $owner))
        ->assertSessionHasErrors();

    expect($organization->roleOf($owner))->toBe(OrganizationRole::Owner);
});

it('refuses to demote the last owner', function () {
    [$organization, $owner] = orgWithProjectAndOwner();

    $this->actingAs($owner)
        ->put(route('settings.members.update', $owner), ['role' => 'admin'])
        ->assertSessionHasErrors();

    expect($organization->roleOf($owner))->toBe(OrganizationRole::Owner);
});

it('lets the last owner be removed once a second owner exists', function () {
    [$organization, $owner] = orgWithProjectAndOwner();
    $secondOwner = inOrg($organization, OrganizationRole::Owner);

    $this->actingAs($secondOwner)
        ->delete(route('settings.members.destroy', $owner))
        ->assertSessionHasNoErrors();

    expect($organization->roleOf($owner))->toBeNull();
});

it('grants and revokes a member their project access through the same update', function () {
    [$organization] = orgWithProjectAndOwner();
    $owner = $organization->users()->wherePivot('role', 'owner')->sole();
    $member = inOrg($organization, OrganizationRole::Member);
    $project = $organization->projects()->sole();

    $this->actingAs($owner)
        ->put(route('settings.members.update', $member), ['role' => 'member', 'projects' => [$project->id]])
        ->assertSessionHasNoErrors();

    expect($member->fresh()->projects->pluck('id')->all())->toBe([$project->id]);

    $this->actingAs($owner)
        ->put(route('settings.members.update', $member), ['role' => 'member', 'projects' => []])
        ->assertSessionHasNoErrors();

    expect($member->fresh()->projects->count())->toBe(0);
});

it('never wipes a grant the member holds in a different organization', function () {
    [$organizationA, $ownerA] = orgWithProjectAndOwner();
    $projectA = $organizationA->projects()->sole();
    $member = inOrg($organizationA, OrganizationRole::Member);
    $member->projects()->attach($projectA);

    $organizationB = Organization::factory()->create();
    $projectB = Project::factory()->for($organizationB)->create();
    $organizationB->users()->attach($member, ['role' => OrganizationRole::Member->value]);
    $member->projects()->attach($projectB);

    $this->actingAs($ownerA)
        ->put(route('settings.members.update', $member), ['role' => 'member', 'projects' => []]);

    expect($member->fresh()->projects->pluck('id')->all())->toBe([$projectB->id]);
});
