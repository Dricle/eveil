<?php

use App\Http\Requests\DeliverabilityTestRequest;
use App\Services\Outreach\DeliverabilityReport;
use App\Services\Outreach\SenderAuthentication;
use Illuminate\Support\Facades\Http;
use Illuminate\Testing\TestResponse;

beforeEach(function () {
    config()->set('eveil.edition', 'cloud');
    config()->set('services.turnstile.secret_key', 'test-secret');
    config()->set('services.mail_tester.domain', 'test.example.net');
    config()->set('services.mail_tester.secret', 'shared-secret');

    $this->app->instance(SenderAuthentication::class, new class extends SenderAuthentication
    {
        protected function txt(string $host): array
        {
            return match ($host) {
                'example.com' => ['v=spf1 include:_spf.google.com ~all'],
                '_dmarc.example.com' => ['v=DMARC1; p=quarantine; rua=mailto:dmarc@example.com'],
                default => [],
            };
        }
    });
});

/**
 * @param  list<string>  $headers
 */
function testMail(array $headers, string $body = 'Hello, a short note.'): string
{
    return implode("\r\n", [...$headers, 'MIME-Version: 1.0', 'Content-Type: text/plain; charset=utf-8', '', $body]);
}

function goodHeaders(): array
{
    return [
        'Authentication-Results: mx.cloudflare.net; spf=pass smtp.mailfrom=example.com; dkim=pass header.d=example.com; dmarc=pass header.from=example.com',
        'DKIM-Signature: v=1; a=rsa-sha256; d=example.com; s=google; b=abc',
        'From: Ada <ada@example.com>',
        'Subject: Quick question about your shop',
        'List-Unsubscribe: <mailto:unsubscribe@example.com>',
        'List-Unsubscribe-Post: List-Unsubscribe=One-Click',
    ];
}

/**
 * @param  array{checks: list<array{label: string, status: string, detail: string}>}  $report
 * @return array<string, string>
 */
function statuses(array $report): array
{
    return array_column($report['checks'], 'status', 'label');
}

function postInbound(string $to, string $raw, ?string $secret = 'shared-secret'): TestResponse
{
    return test()->call('POST', route('tools.deliverability.inbound'), server: [
        'HTTP_X_ENVELOPE_TO' => $to,
        'HTTP_X_SIGNATURE' => hash_hmac('sha256', $to."\n".$raw, (string) $secret),
        'CONTENT_TYPE' => 'message/rfc822',
    ], content: $raw);
}

function createTestAddress(): string
{
    Http::fake([DeliverabilityTestRequest::TURNSTILE_VERIFY_URL => Http::response(['success' => true])]);

    $location = test()->post(route('tools.deliverability.store'), ['cf-turnstile-response' => 'token'])->headers->get('Location');

    return basename((string) $location);
}

it('gives full marks to a mail that authenticates and carries what filters expect', function () {
    $report = app(DeliverabilityReport::class)->grade(testMail(goodHeaders()));

    expect($report['from'])->toBe('ada@example.com')
        ->and(array_unique(statuses($report)))->toBe(['SPF result' => 'pass'])
        ->and($report['score'])->toBe(10.0);
});

it('trusts only the topmost authentication results, and only from the receiving server', function () {
    $forged = ['Authentication-Results: mail.example.com; spf=pass; dkim=pass; dmarc=pass', ...array_slice(goodHeaders(), 1)];

    $statuses = statuses(app(DeliverabilityReport::class)->grade(testMail($forged)));

    expect($statuses['SPF result'])->toBe('unknown')
        ->and($statuses['DKIM result'])->toBe('unknown')
        ->and($statuses['DMARC result'])->toBe('unknown');
});

it('flags what spam filters hold against a mail', function () {
    $report = app(DeliverabilityReport::class)->grade(testMail([
        'Authentication-Results: mx.cloudflare.net; spf=softfail; dkim=none; dmarc=fail',
        'From: ada@other.org',
        'Subject: LAST CHANCE TO BUY',
    ], 'See https://bit.ly/abc'));

    expect(statuses($report))->toMatchArray([
        'SPF result' => 'fail',
        'DKIM result' => 'fail',
        'DMARC result' => 'fail',
        'DKIM signature' => 'fail',
        'SPF record' => 'fail',
        'Unsubscribe header' => 'warn',
        'Links' => 'fail',
        'Subject' => 'warn',
    ])->and($report['score'])->toBeLessThan(5.0);
});

it('hands out a test address, then shows the report once the mail arrives', function () {
    config()->set('services.turnstile.site_key', 'site-key');

    $this->get(route('tools.deliverability'))
        ->assertOk()
        ->assertSee('data-sitekey="site-key"', escape: false)
        ->assertDontSee('noindex');

    $token = createTestAddress();
    $address = 't-'.$token.'@test.example.net';

    $this->get(route('tools.deliverability.show', $token))
        ->assertOk()
        ->assertHeader('Refresh', '5')
        ->assertSee($address)
        ->assertSee('<meta name="robots" content="noindex">', escape: false);

    postInbound($address, testMail(goodHeaders()))->assertNoContent();

    $this->get(route('tools.deliverability.show', $token))
        ->assertOk()
        ->assertHeaderMissing('Refresh')
        ->assertSee('10 / 10')
        ->assertSee('Quick question about your shop');
});

it('grades one mail per address and rejects every other', function () {
    $address = 't-'.createTestAddress().'@test.example.net';

    postInbound($address, testMail(goodHeaders()))->assertNoContent();
    postInbound($address, testMail(goodHeaders()))->assertNotFound();
    postInbound('t-0000000000000000@test.example.net', testMail(goodHeaders()))->assertNotFound();
    postInbound('someone@test.example.net', testMail(goodHeaders()))->assertNotFound();
});

it('refuses an inbound mail without the worker signature', function () {
    $address = 't-'.createTestAddress().'@test.example.net';

    postInbound($address, testMail(goodHeaders()), secret: 'wrong-secret')->assertForbidden();
});

it('refuses an address to a visitor who fails the human check', function () {
    Http::fake([DeliverabilityTestRequest::TURNSTILE_VERIFY_URL => Http::response(['success' => false])]);

    $this->post(route('tools.deliverability.store'), ['cf-turnstile-response' => 'token'])
        ->assertSessionHasErrors('turnstile');
});

it('hands out nothing until a receiving domain is configured', function () {
    config()->set('services.mail_tester.domain', null);
    Http::fake();

    $this->post(route('tools.deliverability.store'), ['cf-turnstile-response' => 'token'])->assertNotFound();

    Http::assertNothingSent();
});

it('is not served on a self-hosted instance', function () {
    config()->set('eveil.edition', 'self-hosted');

    $this->get(route('tools.deliverability'))->assertRedirect('/app');
    $this->post(route('tools.deliverability.store'))->assertNotFound();
    postInbound('t-0000000000000000@test.example.net', testMail(goodHeaders()))->assertNotFound();
});
