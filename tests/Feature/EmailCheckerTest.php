<?php

use App\Http\Requests\EmailCheckRequest;
use App\Services\Discovery\EmailCheck;
use App\Services\Outreach\SenderAuthentication;
use App\Support\DisposableDomains;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    config()->set('eveil.edition', 'cloud');
    config()->set('services.turnstile.secret_key', 'test-secret');
});

/**
 * Answers MX lookups and the disposable list from fixed data instead of the
 * network and the database.
 *
 * @param  array<string, list<string>>  $mx
 * @param  list<string>  $disposable
 */
function fakeEmailCheck(array $mx, array $disposable = []): EmailCheck
{
    $disposableDomains = new class($disposable) extends DisposableDomains
    {
        /** @param  list<string>  $domains */
        public function __construct(private array $domains) {}

        public function includes(string $domain): bool
        {
            return in_array($domain, $this->domains, true);
        }
    };

    $authentication = new class extends SenderAuthentication
    {
        protected function txt(string $host): array
        {
            return [];
        }

        protected function mx(string $domain): array
        {
            return [];
        }
    };

    return new class($disposableDomains, $authentication, $mx) extends EmailCheck
    {
        /** @param  array<string, list<string>>  $mxRecords */
        public function __construct(DisposableDomains $disposable, SenderAuthentication $authentication, private array $mxRecords)
        {
            parent::__construct($disposable, $authentication);
        }

        protected function mx(string $domain): array
        {
            return $this->mxRecords[$domain] ?? [];
        }
    };
}

it('calls an address invalid only when it can prove it', function (string $email, array $mx, array $disposable, bool $invalid) {
    expect(fakeEmailCheck($mx, $disposable)->check($email)['invalid'])->toBe($invalid);
})->with([
    'malformed' => ['not-an-address', [], [], true],
    'throwaway domain' => ['someone@mailinator.com', ['mailinator.com' => ['mail.mailinator.com']], ['mailinator.com'], true],
    'domain without mail servers' => ['someone@example.com', [], [], true],
    'domain that receives mail' => ['someone@example.com', ['example.com' => ['mx.example.com']], [], false],
]);

it('names the provider and reads the domain records once mail servers are found', function () {
    $result = fakeEmailCheck(['example.com' => ['aspmx.l.google.com']])->check(' Someone@Example.com ');

    expect($result['domain'])->toBe('example.com')
        ->and($result['provider'])->toBe('Google Workspace or Gmail')
        ->and($result['authentication']['spf']['record'])->toBeNull();
});

it('checks the address once the visitor passes the human check', function () {
    Http::fake([EmailCheckRequest::TURNSTILE_VERIFY_URL => Http::response(['success' => true])]);
    $this->app->instance(EmailCheck::class, fakeEmailCheck(['example.com' => ['mx.example.com']]));

    $this->post(route('tools.email-checker.check'), ['email' => 'someone@example.com', 'cf-turnstile-response' => 'token'])
        ->assertOk()
        ->assertSee('Domain receives mail')
        ->assertSee('mx.example.com');

    Http::assertSent(fn ($request) => $request['secret'] === 'test-secret' && $request['response'] === 'token');

    $this->post(route('tools.email-checker.check'), ['email' => 'not-an-address', 'cf-turnstile-response' => 'token'])
        ->assertOk()
        ->assertSee('Not a valid email address');
});

it('shows the Turnstile widget with the configured site key', function () {
    config()->set('services.turnstile.site_key', 'site-key');

    $this->get(route('tools.email-checker'))
        ->assertOk()
        ->assertSee('data-sitekey="site-key"', escape: false);
});

it('refuses a check that fails the human check', function () {
    Http::fake([EmailCheckRequest::TURNSTILE_VERIFY_URL => Http::response(['success' => false])]);

    $this->post(route('tools.email-checker.check'), ['email' => 'someone@example.com', 'cf-turnstile-response' => 'token'])
        ->assertSessionHasErrors('email');
});

it('refuses every check when no Turnstile secret is configured', function () {
    config()->set('services.turnstile.secret_key', null);
    Http::fake();

    $this->post(route('tools.email-checker.check'), ['email' => 'someone@example.com', 'cf-turnstile-response' => 'token'])
        ->assertSessionHasErrors('email');

    Http::assertNothingSent();
});

it('throttles repeated checks', function () {
    Http::fake([EmailCheckRequest::TURNSTILE_VERIFY_URL => Http::response(['success' => true])]);
    $this->app->instance(EmailCheck::class, fakeEmailCheck([]));

    for ($i = 0; $i < 10; $i++) {
        $this->post(route('tools.email-checker.check'), ['email' => 'someone@example.com', 'cf-turnstile-response' => 'token'])->assertOk();
    }

    $this->post(route('tools.email-checker.check'), ['email' => 'someone@example.com', 'cf-turnstile-response' => 'token'])->assertStatus(429);
});

it('is not served on a self-hosted instance', function () {
    config()->set('eveil.edition', 'self-hosted');

    $this->get(route('tools.email-checker'))->assertRedirect('/app');
    $this->post(route('tools.email-checker.check'), ['email' => 'someone@example.com'])->assertNotFound();
});
