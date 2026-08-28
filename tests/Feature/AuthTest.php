<?php

use App\Enums\OrganizationRole;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Auth\Notifications\VerifyEmail;
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

    expect($user->is_super_admin)->toBeTrue()
        // Whoever ran the setup screen owns the box; nobody is confirming
        // an address for them.
        ->and($user->email_verified_at)->not->toBeNull();

    $organization = Organization::query()->sole();

    expect($organization->name)->toBe('Acme Tools')
        ->and($organization->slug)->toBe('acme-tools')
        ->and($organization->roleOf($user))->toBe(OrganizationRole::Owner);

    $this->assertAuthenticatedAs($user);
});

it('grants trial credits at setup too, on the cloud edition', function () {
    config()->set('eveil.edition', 'cloud');

    $this->post(route('setup'), [
        'name' => 'Ada',
        'organization' => 'Acme Tools',
        'email' => 'ada@example.test',
        'password' => 'correct-horse-battery',
        'password_confirmation' => 'correct-horse-battery',
    ])->assertSessionHasNoErrors();

    expect(Organization::query()->sole()->credits_balance)->toBeGreaterThan(0);
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

it('sends an Inertia location visit on logout, not a redirect the SPA would try to render', function () {
    // `/` isn't an Inertia page, so a plain redirect leaves Inertia's client
    // trying to swap its own page data into non-Inertia HTML. A 409 with
    // `X-Inertia-Location` is what tells the client to leave the SPA and do
    // a real `window.location` visit instead.
    $this->actingAs(User::factory()->create())
        ->withHeaders(['X-Inertia' => 'true'])
        ->post(route('logout'))
        ->assertStatus(409)
        ->assertHeader('X-Inertia-Location', '/');

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
    Notification::fake();

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
        ->and($organization->roleOf($user))->toBe(OrganizationRole::Owner)
        // A stranger's claim on an address, unlike setup or an accepted
        // invitation: this is the one path `MustVerifyEmail` exists for.
        ->and($user->email_verified_at)->toBeNull();

    $this->assertAuthenticatedAs($user);

    Notification::assertSentTo($user, VerifyEmail::class);
});

it('grants trial credits to a freshly registered organization on the cloud edition', function () {
    config()->set('eveil.edition', 'cloud');

    $this->post(route('register.store'), [
        'name' => 'Ada',
        'organization' => 'Acme Tools',
        'email' => 'ada@example.test',
        'password' => 'correct-horse-battery',
        'password_confirmation' => 'correct-horse-battery',
    ])->assertSessionHasNoErrors();

    $organization = Organization::query()->sole();

    // The FIRST organization every user gets, via `CreateAccount` — not the
    // "add another organization" path `OrganizationController::store()`
    // covers, which is the only one this was ever wired into before.
    expect($organization->credits_balance)->toBeGreaterThan(0)
        ->and($organization->isOnTrial())->toBeTrue();
});

it('grants no credits at all on self-hosted registration', function () {
    config()->set('eveil.edition', 'self');

    $this->post(route('register.store'), [
        'name' => 'Ada',
        'organization' => 'Acme Tools',
        'email' => 'ada@example.test',
        'password' => 'correct-horse-battery',
        'password_confirmation' => 'correct-horse-battery',
    ]);

    expect(Organization::query()->sole()->credits_balance)->toBe(0);
});

it('stashes a URL pasted before registering, for the project-create screen to pick up', function () {
    Notification::fake();

    $this->post(route('register.store'), [
        'name' => 'Ada',
        'organization' => 'Acme Tools',
        'email' => 'ada@example.test',
        'password' => 'correct-horse-battery',
        'password_confirmation' => 'correct-horse-battery',
        'url' => 'https://acme.test',
    ])->assertRedirect(route('dashboard'));

    expect(session('pending_project_url'))->toBe('https://acme.test');
});

it('never stashes a malformed URL, and registration succeeds regardless', function () {
    Notification::fake();

    $this->post(route('register.store'), [
        'name' => 'Ada',
        'organization' => 'Acme Tools',
        'email' => 'ada@example.test',
        'password' => 'correct-horse-battery',
        'password_confirmation' => 'correct-horse-battery',
        'url' => 'not a url',
    ])->assertRedirect(route('dashboard'))->assertSessionHasNoErrors();

    expect(session('pending_project_url'))->toBeNull();
});

it('keeps a freshly registered, unverified user off the app until they verify', function () {
    Notification::fake();

    $this->post(route('register.store'), [
        'name' => 'Ada',
        'organization' => 'Acme Tools',
        'email' => 'ada@example.test',
        'password' => 'correct-horse-battery',
        'password_confirmation' => 'correct-horse-battery',
    ]);

    $user = User::query()->sole();

    $this->get(route('dashboard'))->assertRedirect(route('verification.notice'));

    // Resending doesn't verify anything by itself, just proves the guard
    // above didn't block the one route an unverified user needs.
    $this->post(route('verification.send'))->assertSessionHasNoErrors();
    Notification::assertSentToTimes($user, VerifyEmail::class, 2);
    // The 2nd is the resend above; the 1st came from registering, sent by
    // Laravel's own default wiring for the `Registered` event, not
    // anything this app registers itself.

    // `assertSentTo`'s callback runs once per matching notification sent
    // (two by now), so clicking the link from inside it would fire the
    // click twice; pull the most recent one out and click it on its own.
    $verifyUrl = Notification::sent($user, VerifyEmail::class)->last()->toMail($user)->actionUrl;

    // No `?verified=1`: hitting the dashboard unverified, above, already
    // stored it as the session's "intended" URL (`Redirect::guest()` does
    // that), and `redirect()->intended()` prefers it over Fortify's own
    // fallback.
    $this->get($verifyUrl)->assertRedirect(route('dashboard'));

    expect($user->fresh()->hasVerifiedEmail())->toBeTrue();

    // Past the `verified` gate now: a fresh organization with no project of
    // its own yet redirects to create one, a different reason than before.
    $this->get(route('dashboard'))->assertRedirect(route('projects.create'));
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
