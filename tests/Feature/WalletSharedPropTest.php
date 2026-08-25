<?php

use App\Models\Organization;
use App\Models\Project;
use App\Models\User;

function walletUser(): array
{
    $organization = Organization::factory()->create();
    $user = User::factory()->create();
    $organization->users()->attach($user, ['role' => 'owner']);

    return [$organization, $user];
}

it('shares no wallet on self-hosted, even for a cloud-shaped organization', function () {
    config()->set('eveil.edition', 'self');
    [$organization, $user] = walletUser();
    Project::factory()->for($organization)->create();
    $organization->forceFill(['credits_balance' => 1234])->save();

    $this->actingAs($user)->get(route('dashboard'))
        ->assertInertia(fn ($page) => $page->where('wallet', null));
});

it('shares no wallet while no project is selected', function () {
    config()->set('eveil.edition', 'cloud');
    $user = User::factory()->create();

    // `dashboard` sits behind `project.require` and redirects a projectless
    // user elsewhere; `projects.create` is deliberately outside that
    // middleware, so it is the one Inertia page reachable with none selected.
    $this->actingAs($user)->get(route('projects.create'))
        ->assertInertia(fn ($page) => $page->where('wallet', null));
});

it('shares a zero balance for a fresh cloud organization', function () {
    config()->set('eveil.edition', 'cloud');
    [$organization, $user] = walletUser();
    Project::factory()->for($organization)->create();

    $this->actingAs($user)->get(route('dashboard'))
        ->assertInertia(fn ($page) => $page->where('wallet.balance', 0));
});

it('shares the current organization\'s real balance on cloud', function () {
    config()->set('eveil.edition', 'cloud');
    [$organization, $user] = walletUser();
    Project::factory()->for($organization)->create();
    $organization->forceFill(['credits_balance' => 4321])->save();

    $this->actingAs($user)->get(route('dashboard'))
        ->assertInertia(fn ($page) => $page->where('wallet.balance', 4321));
});
