<?php

use App\Cloud\Actions\AutoTopUp;
use App\Models\Organization;

/**
 * The Stripe charge itself needs a real test-mode key to exercise end to
 * end (same gap as checkout - tracked as a GitHub issue), so nothing here
 * reaches `Organization::charge()`. What is verified is everything AutoTopUp decides
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

it('never attempts a charge that would exceed the monthly cap', function () {
    $organization = Organization::factory()->create([
        'auto_topup_threshold' => 100,
        'auto_topup_amount_cents' => 2000,
    ]);
    $organization->forceFill([
        'pm_type' => 'card',
        'credits_balance' => 10,
        'auto_topup_monthly_cap_cents' => 1000,
    ])->save();

    app(AutoTopUp::class)->maybeTrigger($organization->fresh());

    expect($organization->fresh()->auto_topup_locked_until)->toBeNull();
});

it('allows a charge that stays within the monthly cap', function () {
    $organization = Organization::factory()->create();
    $organization->forceFill(['auto_topup_monthly_cap_cents' => 2000])->save();

    expect($organization->autoTopUpCapExceededBy(2000))->toBeFalse()
        ->and($organization->autoTopUpCapExceededBy(2001))->toBeTrue();
});

it('resets the monthly auto top-up spend counter in a new calendar month', function () {
    $organization = Organization::factory()->create();
    $organization->forceFill([
        'auto_topup_monthly_cap_cents' => 1000,
        'auto_topup_spent_cents' => 900,
        'auto_topup_spent_month' => now()->subMonthNoOverflow()->startOfMonth()->toDateString(),
    ])->save();

    expect($organization->autoTopUpCapExceededBy(500))->toBeFalse();

    $organization->recordAutoTopUpSpend(500);

    expect($organization->fresh()->auto_topup_spent_cents)->toBe(500)
        ->and($organization->fresh()->auto_topup_spent_month)->toBe(now()->startOfMonth()->toDateString());
});
