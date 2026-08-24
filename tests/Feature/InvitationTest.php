<?php

use App\Enums\OrganizationRole;
use App\Mail\OrganizationInvitationMail;
use App\Models\Organization;
use App\Models\Project;
use App\Models\User;
use Illuminate\Support\Facades\Mail;

function memberOf(Organization $organization, OrganizationRole $role): User
{
    $user = User::factory()->create();
    $organization->users()->attach($user, ['role' => $role->value]);

    return $user;
}

function orgWithProject(): Organization
{
    $organization = Organization::factory()->create();
    Project::factory()->for($organization)->create();

    return $organization;
}

beforeEach(function () {
    Mail::fake();
});

it('lets an owner invite a member', function () {
    $organization = orgWithProject();
    $owner = memberOf($organization, OrganizationRole::Owner);

    $this->actingAs($owner)
        ->post(route('settings.members.store'), ['email' => 'newbie@example.test', 'role' => 'member'])
        ->assertSessionHasNoErrors();

    // ShouldQueue: `Mail::send()` on a queueable mailable queues it rather
    // than sending it inline, which is exactly what MailFake distinguishes.
    Mail::assertQueued(
        OrganizationInvitationMail::class,
        fn (OrganizationInvitationMail $mail): bool => str_contains($mail->acceptUrl, (string) $organization->id)
            && str_contains($mail->acceptUrl, 'signature='),
    );
});

it('lets an admin invite too', function () {
    $organization = orgWithProject();
    $admin = memberOf($organization, OrganizationRole::Admin);

    $this->actingAs($admin)
        ->post(route('settings.members.store'), ['email' => 'newbie@example.test', 'role' => 'admin'])
        ->assertSessionHasNoErrors();

    Mail::assertQueued(OrganizationInvitationMail::class);
});

it('refuses a plain member the right to invite', function () {
    $organization = orgWithProject();
    $member = memberOf($organization, OrganizationRole::Member);
    // Granted the project so the request reaches the authorization check at
    // all: with none, `project.require` redirects a projectless user before
    // the controller is ever reached, which is a different failure than the
    // one this test is about.
    $member->projects()->attach($organization->projects()->sole());

    $this->actingAs($member)
        ->post(route('settings.members.store'), ['email' => 'newbie@example.test', 'role' => 'member'])
        ->assertForbidden();

    Mail::assertNothingQueued();
});

it('never lets owner be invited as a role', function () {
    $organization = orgWithProject();
    $owner = memberOf($organization, OrganizationRole::Owner);

    $this->actingAs($owner)
        ->post(route('settings.members.store'), ['email' => 'newbie@example.test', 'role' => 'owner'])
        ->assertSessionHasErrors('role');
});

it('refuses inviting an email already in the organization', function () {
    $organization = orgWithProject();
    $owner = memberOf($organization, OrganizationRole::Owner);
    $existing = memberOf($organization, OrganizationRole::Member);

    $this->actingAs($owner)
        ->post(route('settings.members.store'), ['email' => $existing->email, 'role' => 'admin'])
        ->assertSessionHasErrors();

    Mail::assertNothingQueued();
});
