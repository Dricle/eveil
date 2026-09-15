<?php

use App\Enums\LinkedinPostFrequency;
use App\Models\Organization;
use App\Models\Project;
use App\Models\User;
use App\Support\CurrentProject;

it('sets the current project posting cadence', function () {
    $user = User::factory()->create();
    $organization = Organization::factory()->create();
    $organization->users()->attach($user, ['role' => 'owner']);
    $project = Project::factory()->for($organization)->create();
    app(CurrentProject::class)->set($project);

    $this->actingAs($user)
        ->put(route('linkedin.posts.cadence'), ['linkedin_post_frequency' => 'weekly'])
        ->assertRedirect(route('linkedin.posts.index'));

    expect($project->fresh()->linkedin_post_frequency)->toBe(LinkedinPostFrequency::Weekly);
});

it('rejects an invalid cadence value', function () {
    $user = User::factory()->create();
    $organization = Organization::factory()->create();
    $organization->users()->attach($user, ['role' => 'owner']);
    $project = Project::factory()->for($organization)->create();
    app(CurrentProject::class)->set($project);

    $this->actingAs($user)
        ->put(route('linkedin.posts.cadence'), ['linkedin_post_frequency' => 'hourly'])
        ->assertInvalid('linkedin_post_frequency');
});
