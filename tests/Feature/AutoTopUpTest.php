<?php

use App\Cloud\Actions\AutoTopUp;
use App\Models\Organization;

/**
 * The Stripe charge itself needs a real test-mode key to exercise end to
 * end (same gap as checkout — noted in TODO.md), so nothing here reaches
 * `Organization::charge()`. What is verified is everything AutoTopUp decides
 * BEFORE it would get there: a wrong guard means either an off-session
 * charge nobody asked for, or a recharge that silently never fires.
 */
it('never attempts a charge when auto top-up is not configured', function () {
    $organization = Organization::factory()->create();
    $organization->forceFill(['credits_balance' => 10])->save();

    app(AutoTopUp::class)->maybeTrigger($organization->fresh());

    expect($organization->fresh()->auto_topup_locked_until)->toBeNull();
});

it('never attempts a charge while the balance is still above the threshold', function () {
    $organization = Organization::factory()->create([
        'auto_topup_threshold' => 100,
        'auto_topup_amount_cents' => 2000,
    ]);
    $organization->forceFill(['pm_type' => 'card', 'credits_balance' => 500])->save();

    app(AutoTopUp::class)->maybeTrigger($organization->fresh());

    expect($organization->fresh()->auto_topup_locked_until)->toBeNull();
});

it('never attempts a charge with no payment method on file', function () {
    $organization = Organization::factory()->create([
        'auto_topup_threshold' => 100,
        'auto_topup_amount_cents' => 2000,
    ]);
    $organization->forceFill(['credits_balance' => 10])->save();

    app(AutoTopUp::class)->maybeTrigger($organization->fresh());

    expect($organization->fresh()->auto_topup_locked_until)->toBeNull();
});

it('claims the auto top-up lock atomically, and refuses a second claim during the cooldown', function () {
    $organization = Organization::factory()->create();

    expect($organization->claimAutoTopUpLock())->toBeTrue()
        ->and($organization->fresh()->auto_topup_locked_until)->not->toBeNull()
        ->and($organization->claimAutoTopUpLock())->toBeFalse();
});

it('claims the lock again once the cooldown has passed', function () {
    $organization = Organization::factory()->create();
    $organization->forceFill(['auto_topup_locked_until' => now()->subMinute()])->save();

    expect($organization->claimAutoTopUpLock())->toBeTrue();
});
