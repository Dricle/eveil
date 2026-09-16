<?php

use App\Models\Organization;
use App\Models\Project;
use App\Models\User;
use App\Support\CurrentProject;

it('sets the current project\'s LinkedIn-specific writing instructions', function () {
    $user = User::factory()->create();
    $organization = Organization::factory()->create();
    $organization->users()->attach($user, ['role' => 'owner']);
    $project = Project::factory()->for($organization)->create();
    app(CurrentProject::class)->set($project);

    $this->actingAs($user)
        ->put(route('linkedin.posts.instructions'), ['linkedin_prompt_instructions' => 'Write in first person, casual tone.'])
        ->assertRedirect(route('linkedin.posts.index'));

    expect($project->fresh()->linkedin_prompt_instructions)->toBe('Write in first person, casual tone.');
});

it('allows clearing the LinkedIn-specific instructions', function () {
    $user = User::factory()->create();
    $organization = Organization::factory()->create();
    $organization->users()->attach($user, ['role' => 'owner']);
    $project = Project::factory()->for($organization)->create(['linkedin_prompt_instructions' => 'Old tone.']);
    app(CurrentProject::class)->set($project);

    $this->actingAs($user)->put(route('linkedin.posts.instructions'), ['linkedin_prompt_instructions' => null]);

    expect($project->fresh()->linkedin_prompt_instructions)->toBeNull();
});

it('rejects instructions over the length limit', function () {
    $user = User::factory()->create();
    $organization = Organization::factory()->create();
    $organization->users()->attach($user, ['role' => 'owner']);
    $project = Project::factory()->for($organization)->create();
    app(CurrentProject::class)->set($project);

    $this->actingAs($user)
        ->put(route('linkedin.posts.instructions'), ['linkedin_prompt_instructions' => str_repeat('a', 2001)])
        ->assertInvalid('linkedin_prompt_instructions');
});
