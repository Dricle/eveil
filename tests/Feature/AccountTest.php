<?php

use App\Models\Organization;
use App\Models\Project;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

it('renders every account screen behind auth', function (string $route) {
    $this->get(route($route))->assertRedirect(route('login'));

    $this->actingAs(User::factory()->create())->get(route($route))->assertOk();
})->with(['account.profile', 'account.password', 'account.two-factor', 'account.delete']);

it('updates the profile', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->put(route('user-profile-information.update'), [
            'name' => 'Ada Lovelace',
            'email' => 'ada@example.test',
        ])->assertSessionHasNoErrors();

    expect($user->fresh()->name)->toBe('Ada Lovelace')
        ->and($user->fresh()->email)->toBe('ada@example.test');
});

it('changes the password only with the current one', function () {
    $user = User::factory()->create(['password' => 'correct-horse-battery']);

    $this->actingAs($user)
        ->put(route('user-password.update'), [
            'current_password' => 'wrong',
            'password' => 'new-correct-horse-battery',
            'password_confirmation' => 'new-correct-horse-battery',
        ])->assertSessionHasErrors('current_password', errorBag: 'updatePassword');

    $this->actingAs($user)
        ->put(route('user-password.update'), [
            'current_password' => 'correct-horse-battery',
            'password' => 'new-correct-horse-battery',
            'password_confirmation' => 'new-correct-horse-battery',
        ])->assertSessionHasNoErrors();

    expect(Hash::check('new-correct-horse-battery', $user->fresh()->password))->toBeTrue();
});

it('refuses to delete the account without the password', function () {
    $user = User::factory()->create(['password' => 'correct-horse-battery']);

    $this->actingAs($user)
        ->delete(route('account.destroy'), ['password' => 'wrong'])
        ->assertSessionHasErrors('password');

    expect(User::query()->whereKey($user->getKey())->exists())->toBeTrue();
    $this->assertAuthenticated();
});

it('deletes the account and the organizations it leaves empty', function () {
    $user = User::factory()->create(['password' => 'correct-horse-battery']);

    $solo = Organization::factory()->create();
    $solo->users()->attach($user, ['role' => 'owner']);
    $project = Project::factory()->for($solo)->create();

    $shared = Organization::factory()->create();
    $shared->users()->attach($user, ['role' => 'member']);
    $shared->users()->attach(User::factory()->create(), ['role' => 'owner']);

    $this->actingAs($user)
        ->delete(route('account.destroy'), ['password' => 'correct-horse-battery'])
        ->assertRedirect('/');

    $this->assertGuest();

    expect(User::query()->whereKey($user->getKey())->exists())->toBeFalse()
        ->and(Organization::query()->whereKey($solo->getKey())->exists())->toBeFalse()
        ->and(Project::query()->whereKey($project->getKey())->exists())->toBeFalse()
        ->and(Organization::query()->whereKey($shared->getKey())->exists())->toBeTrue();
});
