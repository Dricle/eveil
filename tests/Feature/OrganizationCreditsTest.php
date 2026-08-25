<?php

use App\Ai\Agents\WebsiteAnalyst;
use App\Ai\Contracts\SpendGuardInterface;
use App\Ai\OutOfCredit;
use App\Ai\UnmeteredSpend;
use App\Cloud\Ai\CreditSpendGuard;
use App\Cloud\Models\CreditPrice;
use App\Cloud\Models\CreditTransaction;
use App\Models\AgentRun;
use App\Models\Organization;
use App\Models\Project;
use Laravel\Ai\Responses\Data\Meta;
use Laravel\Ai\Responses\Data\Usage;
use Laravel\Ai\Responses\StructuredTextResponse;

// `credits_balance` is deliberately not mass-fillable (never user input), so
// tests reach for it the same way `GrantTrialCredits` does.
function organizationWithBalance(int $balance): Organization
{
    $organization = Organization::factory()->create();
    $organization->forceFill(['credits_balance' => $balance])->save();

    return $organization;
}

beforeEach(function () {
    app()->bind(SpendGuardInterface::class, CreditSpendGuard::class);
    CreditPrice::factory()->create(['agent' => 'website-analyst', 'credits' => 200, 'effective_from' => now()->subDay()]);
});

// The binding is a GLOBAL container override that outlives this file unless
// put back: every other test in the suite calls an agent through
// `SpendGuardInterface`, and finding `CreditSpendGuard` still bound there
// fails them all with "no credits left" for a project with no wallet.
afterEach(function () {
    app()->bind(SpendGuardInterface::class, UnmeteredSpend::class);
});

it('charges the balance only after a successful call, never on a thrown one', function () {
    $organization = organizationWithBalance(1000);
    $project = Project::factory()->for($organization)->create();

    WebsiteAnalyst::fake([
        new StructuredTextResponse(
            ['what_it_does' => 'Widgets.'],
            '{}',
            new Usage(promptTokens: 10, completionTokens: 5),
            new Meta('anthropic', 'claude-opus-5'),
        ),
    ]);

    (new WebsiteAnalyst($project))->prompt('Analyse this.');

    expect($organization->fresh()->credits_balance)->toBe(800)
        ->and(CreditTransaction::sole())
        ->type->toBe('debit')
        ->credits->toBe(-200)
        ->agent->toBe('website-analyst')
        ->agent_run_id->toBe(AgentRun::sole()->id);
});

it('never charges when the provider throws', function () {
    $organization = organizationWithBalance(1000);
    $project = Project::factory()->for($organization)->create();

    WebsiteAnalyst::fake(fn () => throw new RuntimeException('provider exploded'));

    expect(fn () => (new WebsiteAnalyst($project))->prompt('Analyse this.'))->toThrow(RuntimeException::class);

    expect($organization->fresh()->credits_balance)->toBe(1000)
        ->and(CreditTransaction::count())->toBe(0);
});

it('refuses the call outright when the balance cannot cover the price', function () {
    $organization = organizationWithBalance(150);
    $project = Project::factory()->for($organization)->create();

    WebsiteAnalyst::fake(fn () => throw new RuntimeException('the provider was called'));

    expect(fn () => (new WebsiteAnalyst($project))->prompt('Analyse this.'))->toThrow(OutOfCredit::class);
});

it('debits atomically, never past what the organization actually holds', function () {
    $organization = organizationWithBalance(150);

    expect($organization->debit(200))->toBeFalse();

    $organization->refresh();

    expect($organization->credits_balance)->toBe(150)
        ->and($organization->debit(150))->toBeTrue();

    $organization->refresh();

    expect($organization->credits_balance)->toBe(0);
});

it('refuses with a clear message when nobody priced the agent', function () {
    CreditPrice::query()->delete();

    $organization = organizationWithBalance(1000);
    $project = Project::factory()->for($organization)->create();

    WebsiteAnalyst::fake(fn () => throw new RuntimeException('the provider was called'));

    expect(fn () => (new WebsiteAnalyst($project))->prompt('Analyse this.'))
        ->toThrow(OutOfCredit::class, 'No credit price is set');
});
