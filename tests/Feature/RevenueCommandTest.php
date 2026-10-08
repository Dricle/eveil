<?php

use App\Cloud\Models\CreditTransaction;
use App\Models\Organization;

beforeEach(function () {
    config()->set('eveil.edition', 'cloud');
});

it('reports zero plainly when nobody has ever bought credits', function () {
    Organization::factory()->create();

    $this->artisan('eveil:revenue', ['--all' => true])
        ->expectsOutputToContain('No credit purchases')
        ->assertSuccessful();
});

it('totals what was actually collected, and names who paid it', function () {
    $organization = Organization::factory()->create(['name' => 'Friterie Centrale']);

    CreditTransaction::factory()->for($organization)->create([
        'type' => 'grant_purchase',
        'credits' => 20000,
        'amount_cents' => 2000,
        'currency' => 'eur',
    ]);

    $this->artisan('eveil:revenue', ['--all' => true])
        ->expectsOutputToContain('EUR')
        ->expectsOutputToContain('20.00')
        ->expectsOutputToContain('Friterie Centrale')
        ->assertSuccessful();
});

/**
 * A trial grant moves credits without any money behind it. Counting it as
 * revenue is the exact mistake this command exists to prevent.
 */
it('never counts a trial grant or a debit as revenue', function () {
    $organization = Organization::factory()->create();

    CreditTransaction::factory()->for($organization)->create(['type' => 'grant_trial', 'credits' => 5000]);
    CreditTransaction::factory()->for($organization)->create(['type' => 'debit', 'credits' => -200]);

    $this->artisan('eveil:revenue', ['--all' => true])
        ->expectsOutputToContain('No credit purchases')
        ->assertSuccessful();
});

it('reports only the month asked for', function () {
    $organization = Organization::factory()->create();

    CreditTransaction::factory()->for($organization)->create([
        'type' => 'grant_purchase',
        'credits' => 10000,
        'amount_cents' => 1000,
        'currency' => 'eur',
        'created_at' => '2026-09-15 10:00:00',
    ]);

    CreditTransaction::factory()->for($organization)->create([
        'type' => 'grant_purchase',
        'credits' => 50000,
        'amount_cents' => 5000,
        'currency' => 'eur',
        'created_at' => '2026-10-02 10:00:00',
    ]);

    $this->artisan('eveil:revenue', ['--month' => '2026-09'])
        ->expectsOutputToContain('September 2026')
        ->expectsOutputToContain('10.00')
        ->assertSuccessful();
});

/**
 * Summing cents across currencies would add euros to dollars at an exchange
 * rate nobody recorded, and produce a single confident wrong number.
 */
it('keeps currencies apart rather than summing them into one total', function () {
    $organization = Organization::factory()->create();

    CreditTransaction::factory()->for($organization)->create([
        'type' => 'grant_purchase', 'credits' => 10000, 'amount_cents' => 1000, 'currency' => 'eur',
    ]);
    CreditTransaction::factory()->for($organization)->create([
        'type' => 'grant_purchase', 'credits' => 10000, 'amount_cents' => 1000, 'currency' => 'usd',
    ]);

    $this->artisan('eveil:revenue', ['--all' => true])
        ->expectsOutputToContain('EUR')
        ->expectsOutputToContain('USD')
        ->assertSuccessful();
});

/**
 * A purchase written before the amount column existed must be flagged, not
 * quietly dropped: an understated total that looks complete is worse than a
 * stated gap.
 */
it('flags purchases that carry no recorded amount instead of understating the total', function () {
    $organization = Organization::factory()->create();

    CreditTransaction::factory()->for($organization)->create([
        'type' => 'grant_purchase',
        'credits' => 10000,
        'amount_cents' => null,
    ]);

    $this->artisan('eveil:revenue', ['--all' => true])
        ->expectsOutputToContain('no recorded amount')
        ->assertSuccessful();
});

it('rejects a month that is not a month', function () {
    $this->artisan('eveil:revenue', ['--month' => 'last-tuesday'])->assertFailed();
});

it('says there is nothing to bill on a self-hosted instance', function () {
    config()->set('eveil.edition', 'self');

    $this->artisan('eveil:revenue', ['--all' => true])
        ->expectsOutputToContain('self-hosted')
        ->assertSuccessful();
});
