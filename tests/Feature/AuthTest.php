<?php

use App\Enums\OrganizationRole;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Route;
use PragmaRX\Google2FA\Google2FA;

it('sends a fresh instance to setup instead of a dead-end login screen', function () {
    $this->get(route('login'))->assertRedirect(route('setup'));
});

it('creates the super admin and their organization at setup', function () {
    $this->post(route('setup'), [
        'name' => 'Ada',
        'organization' => 'Acme Tools',
        'email' => 'ada@example.test',
        'password' => 'correct-horse-battery',
        'password_confirmation' => 'correct-horse-battery',
    ])->assertRedirect(route('dashboard'));

    $user = User::query()->sole();

    expect($user->is_super_admin)->toBeTrue();

    $organization = Organization::query()->sole();

    expect($organization->name)->toBe('Acme Tools')
        ->and($organization->slug)->toBe('acme-tools')
        ->and($organization->roleOf($user))->toBe(OrganizationRole::Owner);

    $this->assertAuthenticatedAs($user);
});

it('refuses a second setup once an account exists', function () {
    User::factory()->create();

    $this->get(route('setup'))->assertRedirect(route('login'));

    $this->post(route('setup'), [
        'name' => 'Mallory',
        'organization' => 'Takeover',
        'email' => 'mallory@example.test',
        'password' => 'correct-horse-battery',
        'password_confirmation' => 'correct-horse-battery',
    ])->assertForbidden();

    expect(User::query()->count())->toBe(1);
});

it('logs in with the right password and rejects the wrong one', function () {
    $user = User::factory()->create(['password' => 'correct-horse-battery']);

    $this->post(route('login.store'), [
        'email' => $user->email,
        'password' => 'nope',
    ])->assertSessionHasErrors('email');

    $this->assertGuest();

    $this->post(route('login.store'), [
        'email' => $user->email,
        'password' => 'correct-horse-battery',
    ])->assertRedirect(route('dashboard'));

    $this->assertAuthenticatedAs($user);
});

it('logs out', function () {
    $this->actingAs(User::factory()->create())
        ->post(route('logout'))
        ->assertRedirect('/');

    $this->assertGuest();
});

it('keeps guests off the application', function () {
    $this->get(route('dashboard'))->assertRedirect(route('login'));
    $this->get(route('account.profile'))->assertRedirect(route('login'));
});

it('emails a reset link and lets the user set a new password', function () {
    Notification::fake();

    $user = User::factory()->create();

    $this->post(route('password.email'), ['email' => $user->email])
        ->assertSessionHasNoErrors();

    Notification::assertSentTo($user, ResetPassword::class, function (ResetPassword $notification) use ($user) {
        $this->post(route('password.update'), [
            'token' => $notification->token,
            'email' => $user->email,
            'password' => 'new-correct-horse-battery',
            'password_confirmation' => 'new-correct-horse-battery',
        ])->assertSessionHasNoErrors();

        return true;
    });

    $this->post(route('login.store'), [
        'email' => $user->email,
        'password' => 'new-correct-horse-battery',
    ])->assertRedirect(route('dashboard'));

    $this->assertAuthenticatedAs($user->fresh());
});

it('challenges for a second factor once two-factor is confirmed', function () {
    $user = User::factory()->create(['password' => 'correct-horse-battery']);

    $this->actingAs($user)
        ->withSession(['auth.password_confirmed_at' => time()])
        ->post(route('two-factor.enable'))
        ->assertSessionHasNoErrors();

    $user->refresh();

    expect($user->two_factor_secret)->not->toBeNull()
        ->and($user->two_factor_confirmed_at)->toBeNull();

    $secret = decrypt($user->two_factor_secret);

    $this->actingAs($user)
        ->withSession(['auth.password_confirmed_at' => time()])
        ->post(route('two-factor.confirm'), [
            'code' => app(Google2FA::class)->getCurrentOtp($secret),
        ])->assertSessionHasNoErrors();

    expect($user->fresh()->two_factor_confirmed_at)->not->toBeNull();

    $this->post(route('logout'));

    $this->post(route('login.store'), [
        'email' => $user->email,
        'password' => 'correct-horse-battery',
    ])->assertRedirect(route('two-factor.login'));

    $this->assertGuest();

    // A recovery code rather than a fresh OTP: Fortify caches the code just
    // used to confirm enrolment, so replaying it inside the same window is
    // refused on purpose.
    $this->post(route('two-factor.login.store'), [
        'recovery_code' => $user->fresh()->recoveryCodes()[0],
    ])->assertRedirect(route('dashboard'));

    $this->assertAuthenticatedAs($user->fresh());
});

it('does not register the sign-up routes when sign-ups are closed', function () {
    $_SERVER['REGISTRATION_ENABLED'] = 'false';

    try {
        $this->refreshApplication();

        expect(Route::has('register'))->toBeFalse();

        $this->get('/app/register')->assertNotFound();
        $this->post('/app/register')->assertNotFound();
    } finally {
        $_SERVER['REGISTRATION_ENABLED'] = 'true';
    }
});

it('registers a user with their own organization when sign-ups are open', function () {
    $this->post(route('register.store'), [
        'name' => 'Ada',
        'organization' => 'Acme Tools',
        'email' => 'ada@example.test',
        'password' => 'correct-horse-battery',
        'password_confirmation' => 'correct-horse-battery',
    ])->assertRedirect(route('dashboard'));

    $user = User::query()->sole();
    $organization = Organization::query()->sole();

    expect($user->is_super_admin)->toBeFalse()
        ->and($organization->roleOf($user))->toBe(OrganizationRole::Owner);

    $this->assertAuthenticatedAs($user);
});

it('gives two organizations of the same name distinct slugs', function () {
    foreach (['ada@example.test', 'grace@example.test'] as $email) {
        $this->post(route('register.store'), [
            'name' => 'Someone',
            'organization' => 'Acme Tools',
            'email' => $email,
            'password' => 'correct-horse-battery',
            'password_confirmation' => 'correct-horse-battery',
        ])->assertSessionHasNoErrors();

        $this->post(route('logout'));
    }

    expect(Organization::query()->pluck('slug')->all())->toBe(['acme-tools', 'acme-tools-2']);
});

it('shows the marketing homepage only on the cloud edition', function () {
    config()->set('eveil.edition', 'self');
    $this->get('/')->assertRedirect('/app');

    config()->set('eveil.edition', 'cloud');
    $this->get('/')->assertOk()->assertSee(config('app.name'), false);
});
