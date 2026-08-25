<?php

use App\Models\Organization;
use App\Models\Project;
use App\Models\User;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;

function trialOwner(): array
{
    $organization = Organization::factory()->create();
    $user = User::factory()->create();
    $organization->users()->attach($user, ['role' => 'owner']);

    return [$organization, $user];
}

beforeEach(function () {
    Queue::fake();
    Http::fake([
        '*/robots.txt' => Http::response('', 404),
        '*' => Http::response('<!doctype html><html><head><title>Acme</title></head><body>Acme.</body></html>'),
    ]);
});

it('refuses a second project for a trial organization on cloud', function () {
    config()->set('eveil.edition', 'cloud');
    [$organization, $user] = trialOwner();
    Project::factory()->for($organization)->create();

    $this->actingAs($user)
        ->post(route('projects.store'), ['name' => 'Second', 'url' => 'https://acme.test/'])
        ->assertSessionHasErrors();

    expect($organization->fresh()->projects()->count())->toBe(1);
});

it('allows a second project once the organization has paid', function () {
    config()->set('eveil.edition', 'cloud');
    [$organization, $user] = trialOwner();
    Project::factory()->for($organization)->create();
    // stripe_id is deliberately not mass-fillable: Cashier sets it, not user
    // input.
    $organization->forceFill(['stripe_id' => 'cus_test123'])->save();

    $this->actingAs($user)
        ->post(route('projects.store'), ['name' => 'Second', 'url' => 'https://acme.test/'])
        ->assertSessionHasNoErrors();

    expect($organization->fresh()->projects()->count())->toBe(2);
});

it('never limits project count on self-hosted', function () {
    config()->set('eveil.edition', 'self');
    [$organization, $user] = trialOwner();
    Project::factory()->for($organization)->create();

    $this->actingAs($user)
        ->post(route('projects.store'), ['name' => 'Second', 'url' => 'https://acme.test/'])
        ->assertSessionHasNoErrors();

    expect($organization->fresh()->projects()->count())->toBe(2);
});

it('caps trial leads on the very first project, not just credits', function () {
    config()->set('eveil.edition', 'cloud');
    $user = User::factory()->create();
    $organization = Organization::factory()->create();
    $organization->users()->attach($user, ['role' => 'owner']);

    $this->actingAs($user)->post(route('projects.store'), ['name' => 'First', 'url' => 'https://acme.test/']);

    expect($organization->fresh()->projects()->sole()->lead_limit)->toBe(500);
});

it('leaves lead_limit alone once the organization has paid', function () {
    config()->set('eveil.edition', 'cloud');
    $user = User::factory()->create();
    $organization = Organization::factory()->create();
    $organization->users()->attach($user, ['role' => 'owner']);
    $organization->forceFill(['stripe_id' => 'cus_test123'])->save();

    $this->actingAs($user)->post(route('projects.store'), ['name' => 'First', 'url' => 'https://acme.test/']);

    expect($organization->fresh()->projects()->sole()->lead_limit)->toBeNull();
});

it('joins the organization named by organization_id, from the create-org redirect', function () {
    config()->set('eveil.edition', 'cloud');
    $user = User::factory()->create();
    $current = Organization::factory()->create();
    $current->users()->attach($user, ['role' => 'owner']);
    Project::factory()->for($current)->create();
    $target = Organization::factory()->create();
    $target->users()->attach($user, ['role' => 'owner']);

    $this->actingAs($user)->post(route('projects.store'), [
        'name' => 'In target org',
        'url' => 'https://acme.test/',
        'organization_id' => $target->id,
    ])->assertSessionHasNoErrors();

    expect($target->fresh()->projects()->count())->toBe(1)
        ->and($current->fresh()->projects()->count())->toBe(1);
});

it('allows the very first project with nothing selected yet', function () {
    config()->set('eveil.edition', 'cloud');
    $user = User::factory()->create();
    $organization = Organization::factory()->create();
    $organization->users()->attach($user, ['role' => 'owner']);

    $this->actingAs($user)
        ->post(route('projects.store'), ['name' => 'First', 'url' => 'https://acme.test/'])
        ->assertSessionHasNoErrors();

    expect($organization->fresh()->projects()->count())->toBe(1);
});
