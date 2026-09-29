<?php

use App\Models\Organization;
use App\Models\Project;
use App\Models\User;
use App\Support\CurrentProject;

it('shows every writing-tone box together, one per network', function () {
    $user = User::factory()->create();
    $organization = Organization::factory()->create();
    $organization->users()->attach($user, ['role' => 'owner']);
    $project = Project::factory()->for($organization)->create([
        'prompt_instructions' => 'Write in French.',
        'linkedin_prompt_instructions' => 'Be punchy.',
        'x_prompt_instructions' => 'No hashtags.',
    ]);
    app(CurrentProject::class)->set($project);

    $this->actingAs($user)
        ->get(route('settings.ai-instructions.edit'))
        ->assertInertia(fn ($page) => $page
            ->where('promptInstructions', 'Write in French.')
            ->where('postInstructions.linkedin', 'Be punchy.')
            ->where('postInstructions.x', 'No hashtags.')
            ->where('postInstructions.bluesky', null));
});

it("sets the current project's email writing instructions", function () {
    $user = User::factory()->create();
    $organization = Organization::factory()->create();
    $organization->users()->attach($user, ['role' => 'owner']);
    $project = Project::factory()->for($organization)->create();
    app(CurrentProject::class)->set($project);

    $this->actingAs($user)
        ->put(route('settings.ai-instructions.emails.update'), ['prompt_instructions' => 'Write in French. Never use emoji.'])
        ->assertRedirect(route('settings.ai-instructions.edit'));

    expect($project->fresh()->prompt_instructions)->toBe('Write in French. Never use emoji.');
});

it('rejects email instructions over the length limit', function () {
    $user = User::factory()->create();
    $organization = Organization::factory()->create();
    $organization->users()->attach($user, ['role' => 'owner']);
    $project = Project::factory()->for($organization)->create();
    app(CurrentProject::class)->set($project);

    $this->actingAs($user)
        ->put(route('settings.ai-instructions.emails.update'), ['prompt_instructions' => str_repeat('a', 2001)])
        ->assertInvalid('prompt_instructions');
});

it('saves one network\'s tone box without touching the others', function () {
    $user = User::factory()->create();
    $organization = Organization::factory()->create();
    $organization->users()->attach($user, ['role' => 'owner']);
    $project = Project::factory()->for($organization)->create(['x_prompt_instructions' => 'No hashtags.']);
    app(CurrentProject::class)->set($project);

    $this->actingAs($user)
        ->put(route('settings.ai-instructions.posts.update', 'linkedin'), ['instructions' => 'Be punchy.'])
        ->assertRedirect(route('settings.ai-instructions.edit'));

    $this->actingAs($user)
        ->put(route('settings.ai-instructions.posts.update', 'linkedin'), ['instructions' => str_repeat('a', 2001)])
        ->assertSessionHasErrors('instructions');

    expect($project->fresh())
        ->linkedin_prompt_instructions->toBe('Be punchy.')
        ->x_prompt_instructions->toBe('No hashtags.');
});

it('allows clearing a tone box', function () {
    $user = User::factory()->create();
    $organization = Organization::factory()->create();
    $organization->users()->attach($user, ['role' => 'owner']);
    $project = Project::factory()->for($organization)->create(['bluesky_prompt_instructions' => 'Old.']);
    app(CurrentProject::class)->set($project);

    $this->actingAs($user)->put(route('settings.ai-instructions.posts.update', 'bluesky'), ['instructions' => '']);

    expect($project->fresh()->bluesky_prompt_instructions)->toBeNull();
});
