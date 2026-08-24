<?php

use App\Enums\OrganizationRole;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Support\Facades\URL;

function invitationUrl(Organization $organization, string $email, OrganizationRole $role, $expiresIn = null): string
{
    return URL::temporarySignedRoute('invitations.accept', $expiresIn ?? now()->addDays(7), [
        'organization' => $organization->id,
        'email' => $email,
        'role' => $role->value,
    ]);
}

it('creates the account and joins as a guest', function () {
    $organization = Organization::factory()->create();
    $url = invitationUrl($organization, 'newbie@example.test', OrganizationRole::Admin);

    $this->post($url, [
        'name' => 'Ada Lovelace',
        'password' => 'correct-horse-battery',
        'password_confirmation' => 'correct-horse-battery',
    ])->assertSessionHasNoErrors();

    $user = User::query()->where('email', 'newbie@example.test')->sole();

    expect($organization->roleOf($user))->toBe(OrganizationRole::Admin);

    $this->assertAuthenticatedAs($user);
});

it('attaches an already-authenticated user', function () {
    $organization = Organization::factory()->create();
    $user = User::factory()->create();
    $url = invitationUrl($organization, $user->email, OrganizationRole::Member);

    $this->actingAs($user)
        ->post($url)
        ->assertSessionHasNoErrors();

    expect($organization->roleOf($user))->toBe(OrganizationRole::Member);
});

it('refuses a tampered link', function () {
    $organization = Organization::factory()->create();
    $url = invitationUrl($organization, 'newbie@example.test', OrganizationRole::Admin);

    // The role is swapped without touching the signature, exactly what a
    // stateless signed link has to defend against: nothing else checks that
    // the query string was not edited after signing.
    $tampered = str_replace('role=admin', 'role=owner', $url);

    $this->get($tampered)->assertOk(); // the friendly "invalid" page, not a 500
    $this->post($tampered)->assertForbidden();

    expect(User::query()->where('email', 'newbie@example.test')->exists())->toBeFalse();
});

it('refuses an expired invitation', function () {
    $organization = Organization::factory()->create();
    $url = invitationUrl($organization, 'newbie@example.test', OrganizationRole::Admin, now()->subDay());

    $this->post($url, [
        'name' => 'Ada Lovelace',
        'password' => 'correct-horse-battery',
        'password_confirmation' => 'correct-horse-battery',
    ])->assertForbidden();
});

it('refuses a guest accept when the email already has an account', function () {
    $organization = Organization::factory()->create();
    $existing = User::factory()->create(['email' => 'taken@example.test']);
    $url = invitationUrl($organization, 'taken@example.test', OrganizationRole::Member);

    $this->post($url, [
        'name' => 'Somebody else',
        'password' => 'correct-horse-battery',
        'password_confirmation' => 'correct-horse-battery',
    ])->assertSessionHasErrors();

    expect(User::query()->where('email', 'taken@example.test')->count())->toBe(1)
        ->and(User::query()->where('email', 'taken@example.test')->sole()->id)->toBe($existing->id);
});
