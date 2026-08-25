<?php

use App\Models\Organization;
use App\Models\User;

it('creates the first super admin from the environment', function () {
    config([
        'eveil.admin.name' => 'Clement',
        'eveil.admin.email' => 'clement@abcreche.test',
        'eveil.admin.password' => 'a-long-enough-password',
        'eveil.admin.organization' => 'ABCreche',
    ]);

    $this->artisan('eveil:install')->assertSuccessful();

    $user = User::query()->sole();

    expect($user->email)->toBe('clement@abcreche.test')
        ->and($user->is_super_admin)->toBeTrue()
        // Whoever ran this owns the box; nobody is confirming an address
        // for them.
        ->and($user->email_verified_at)->not->toBeNull()
        // Self-hosted runs the same organization code path as cloud: a user
        // without one can own nothing and dies on the first project.
        ->and($user->organizations()->sole()->name)->toBe('ABCreche');
});

it('does nothing at all once an account exists', function () {
    $existing = User::factory()->create(['email' => 'first@abcreche.test']);
    Organization::factory()->create()->users()->attach($existing, ['role' => 'owner']);

    config([
        'eveil.admin.email' => 'second@abcreche.test',
        'eveil.admin.password' => 'a-long-enough-password',
    ]);

    // This runs on every boot from the entrypoint, so a restart must never
    // reset somebody's password or add a second super admin.
    $this->artisan('eveil:install')->assertSuccessful();

    expect(User::query()->count())->toBe(1)
        ->and(User::query()->sole()->email)->toBe('first@abcreche.test');
});

it('leaves the way in to the setup screen when the environment says nothing', function () {
    config(['eveil.admin.email' => null, 'eveil.admin.password' => null]);

    $this->artisan('eveil:install')->assertSuccessful();

    expect(User::query()->exists())->toBeFalse();

    // And that screen is what a fresh instance shows, rather than a 500.
    $this->get(route('setup'))->assertOk();
});

it('refuses a password too short to defend a super admin', function () {
    config([
        'eveil.admin.email' => 'clement@abcreche.test',
        'eveil.admin.password' => 'admin',
    ]);

    $this->artisan('eveil:install')->assertFailed();

    // Deliberately no fallback to a default: an instance on the internet with a
    // known admin password is worse than one nobody can log into yet.
    expect(User::query()->exists())->toBeFalse();
});
