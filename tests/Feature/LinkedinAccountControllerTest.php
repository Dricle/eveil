<?php

use App\Models\LinkedinAccount;
use App\Models\Organization;
use App\Models\Project;
use App\Models\User;
use App\Support\CurrentProject;

it('grants a connected account to chosen projects only', function () {
    $user = User::factory()->create();
    $organization = Organization::factory()->create();
    $organization->users()->attach($user, ['role' => 'owner']);
    $project = Project::factory()->for($organization)->create();
    $other = Project::factory()->for($organization)->create();

    app(CurrentProject::class)->set($project);

    $account = LinkedinAccount::factory()->create(['organization_id' => $organization->id]);

    $this->actingAs($user)
        ->put(route('linkedin.account.update', $account), ['projects' => [$project->id]])
        ->assertRedirect(route('linkedin.account.index'));

    expect($account->projects->pluck('id')->all())->toBe([$project->id]);
    expect($other->fresh()->linkedinAccounts()->count())->toBe(0);
});

it('cannot manage another organization account', function () {
    $user = User::factory()->create();
    $organization = Organization::factory()->create();
    $organization->users()->attach($user, ['role' => 'owner']);
    $project = Project::factory()->for($organization)->create();
    app(CurrentProject::class)->set($project);

    $theirs = LinkedinAccount::factory()->create();

    $this->actingAs($user)->delete(route('linkedin.account.destroy', $theirs))->assertNotFound();
});
