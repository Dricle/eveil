<?php

use App\Ai\Contracts\SpendGuardInterface;
use App\Ai\UnmeteredSpend;
use App\Cloud\Ai\CreditSpendGuard;
use App\Cloud\CloudServiceProvider;

/**
 * `register()` is what self-gates on `eveil.edition`, called directly here
 * rather than rebooting the whole application with a different `APP_EDITION`:
 * `.env` already sets that key, so Dotenv's immutability means a later
 * `refreshApplication()` cannot override it the way `AuthTest`'s
 * `REGISTRATION_ENABLED` trick can for a key `.env` never claimed first.
 * Exercising the provider's own logic directly is the reliable way to prove
 * it, independent of that.
 *
 * The container binding is GLOBAL and outlives one test unless put back:
 * every other test in the whole suite calls an agent through
 * `SpendGuardInterface`, so leaving it on `CreditSpendGuard` after this file
 * runs fails dozens of unrelated tests with "no credits left" — always
 * restore it, pass or fail.
 */
it('binds the credit guard when cloud', function () {
    config()->set('eveil.edition', 'cloud');

    try {
        (new CloudServiceProvider($this->app))->register();

        expect(app(SpendGuardInterface::class))->toBeInstanceOf(CreditSpendGuard::class);
    } finally {
        app()->bind(SpendGuardInterface::class, UnmeteredSpend::class);
        config()->set('eveil.edition', 'self');
    }
});

it('never rebinds the credit guard on self-hosted', function () {
    config()->set('eveil.edition', 'self');
    app()->bind(SpendGuardInterface::class, UnmeteredSpend::class);

    (new CloudServiceProvider($this->app))->register();

    expect(app(SpendGuardInterface::class))->toBeInstanceOf(UnmeteredSpend::class);
});
