<?php

use App\Models\Organization;
use App\Models\Project;
use App\Models\SocialAccount;
use App\Models\User;
use App\Support\CurrentProject;

it('lists only this organization\'s LinkedIn accounts', function () {
    $user = User::factory()->create();
    $organization = Organization::factory()->create();
    $organization->users()->attach($user, ['role' => 'owner']);
    $project = Project::factory()->for($organization)->create();
    app(CurrentProject::class)->set($project);

    SocialAccount::factory()->linkedin()->create(['organization_id' => $organization->id]);
    SocialAccount::factory()->create(['organization_id' => $organization->id]);
    SocialAccount::factory()->linkedin()->create();

    $this->actingAs($user)->get(route('settings.linkedin.index'))
        ->assertInertia(fn ($page) => $page->component('settings/Linkedin')->has('accounts', 1));
});

it('grants a connected account to chosen projects only', function () {
    $user = User::factory()->create();
    $organization = Organization::factory()->create();
    $organization->users()->attach($user, ['role' => 'owner']);
    $project = Project::factory()->for($organization)->create();
    $other = Project::factory()->for($organization)->create();

    app(CurrentProject::class)->set($project);

    $account = SocialAccount::factory()->linkedin()->create(['organization_id' => $organization->id]);

    $this->actingAs($user)
        ->from(route('settings.linkedin.index'))
        ->put(route('settings.social-accounts.update', $account), ['projects' => [$project->id]])
        ->assertRedirect(route('settings.linkedin.index'));

    expect($account->projects->pluck('id')->all())->toBe([$project->id]);
    expect($other->fresh()->socialAccounts()->count())->toBe(0);
});

it('cannot manage another organization account', function () {
    $user = User::factory()->create();
    $organization = Organization::factory()->create();
    $organization->users()->attach($user, ['role' => 'owner']);
    $project = Project::factory()->for($organization)->create();
    app(CurrentProject::class)->set($project);

    $theirs = SocialAccount::factory()->linkedin()->create();

    $this->actingAs($user)->delete(route('settings.social-accounts.destroy', $theirs))->assertNotFound();
});
