<?php

use App\Services\Outreach\SenderAuthentication;

beforeEach(function () {
    config()->set('eveil.edition', 'cloud');
});

/**
 * Answers DNS from a fixed zone instead of the network.
 *
 * @param  array<string, list<string>>  $txt
 * @param  array<string, list<string>>  $mx
 */
function fakeDns(array $txt, array $mx = []): SenderAuthentication
{
    return new class($txt, $mx) extends SenderAuthentication
    {
        /**
         * @param  array<string, list<string>>  $txtRecords
         * @param  array<string, list<string>>  $mxRecords
         */
        public function __construct(private array $txtRecords, private array $mxRecords) {}

        protected function txt(string $host): array
        {
            return $this->txtRecords[$host] ?? [];
        }

        protected function mx(string $domain): array
        {
            return $this->mxRecords[$domain] ?? [];
        }
    };
}

it('passes a domain whose records are sound', function () {
    $result = fakeDns([
        'example.com' => ['google-site-verification=abc', 'v=spf1 include:_spf.google.com ~all'],
        '_spf.google.com' => ['v=spf1 include:_netblocks.google.com ~all'],
        '_dmarc.example.com' => ['v=DMARC1; p=quarantine; rua=mailto:dmarc@example.com'],
    ])->check('example.com');

    expect($result['spf']['record'])->toBe('v=spf1 include:_spf.google.com ~all')
        ->and($result['spf']['problems'])->toBe([])
        ->and($result['dmarc']['problems'])->toBe([]);
});

it('suggests an SPF record that covers the mail host', function () {
    $result = fakeDns([], ['example.com' => ['aspmx.l.google.com']])->check('example.com');

    expect($result['spf']['record'])->toBeNull()
        ->and($result['spf']['suggestion'])->toBe('v=spf1 include:_spf.google.com ~all')
        ->and($result['dmarc']['suggestion'])->toBe('v=DMARC1; p=none; rua=mailto:dmarc@example.com');
});

it('flags two SPF records and a record that lets anyone send', function () {
    $result = fakeDns([
        'example.com' => ['v=spf1 mx +all', 'v=spf1 a -all'],
    ])->check('example.com');

    expect($result['spf']['problems'])->toHaveCount(2)
        ->and($result['spf']['problems'][0])->toContain('More than one SPF record')
        ->and($result['spf']['problems'][1])->toContain('+all');
});

it('flags a record that needs more than ten lookups once includes are followed', function () {
    $txt = ['example.com' => ['v=spf1 include:a.test include:b.test -all']];
    $txt['a.test'] = ['v=spf1 '.implode(' ', array_map(fn (int $i): string => "include:a{$i}.test", range(1, 5))).' -all'];
    $txt['b.test'] = ['v=spf1 '.implode(' ', array_map(fn (int $i): string => "include:b{$i}.test", range(1, 5))).' -all'];

    $result = fakeDns($txt)->check('example.com');

    expect($result['spf']['problems'])->toHaveCount(1)
        ->and($result['spf']['problems'][0])->toContain('more than 10 DNS lookups');
});

it('does not loop on includes that point at each other', function () {
    $result = fakeDns([
        'example.com' => ['v=spf1 include:loop.test -all'],
        'loop.test' => ['v=spf1 include:example.com -all'],
    ])->check('example.com');

    expect($result['spf']['problems'])->toBe([]);
});

it('flags a DMARC policy that only monitors and sends no reports', function () {
    $result = fakeDns(['_dmarc.example.com' => ['v=DMARC1; p=none']])->check('example.com');

    expect($result['dmarc']['problems'])->toHaveCount(2)
        ->and($result['dmarc']['problems'][0])->toContain('p=none')
        ->and($result['dmarc']['problems'][1])->toContain('rua=');
});

it('checks the domain behind whatever the visitor pasted', function () {
    $this->app->instance(SenderAuthentication::class, fakeDns([
        'example.com' => ['v=spf1 mx -all'],
    ]));

    $this->post(route('tools.spf-dmarc.check'), ['domain' => ' https://www.Example.com/contact '])
        ->assertOk()
        ->assertSee('v=spf1 mx -all')
        ->assertSee('_dmarc.example.com');
});

it('rejects something that is not a domain', function () {
    $this->post(route('tools.spf-dmarc.check'), ['domain' => 'not a domain'])
        ->assertSessionHasErrors('domain');
});

it('throttles repeated checks', function () {
    $this->app->instance(SenderAuthentication::class, fakeDns([]));

    for ($i = 0; $i < 10; $i++) {
        $this->post(route('tools.spf-dmarc.check'), ['domain' => 'example.com'])->assertOk();
    }

    $this->post(route('tools.spf-dmarc.check'), ['domain' => 'example.com'])->assertStatus(429);
});

it('is not served on a self-hosted instance', function () {
    config()->set('eveil.edition', 'self-hosted');

    $this->get(route('tools.spf-dmarc'))->assertRedirect('/app');
    $this->post(route('tools.spf-dmarc.check'), ['domain' => 'example.com'])->assertNotFound();
});
