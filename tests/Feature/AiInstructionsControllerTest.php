<?php

use App\Models\Organization;
use App\Models\Project;
use App\Models\User;
use App\Support\CurrentProject;

it('shows both writing-tone boxes together', function () {
    $user = User::factory()->create();
    $organization = Organization::factory()->create();
    $organization->users()->attach($user, ['role' => 'owner']);
    $project = Project::factory()->for($organization)->create([
        'prompt_instructions' => 'Write in French.',
        'linkedin_prompt_instructions' => 'Be punchy.',
    ]);
    app(CurrentProject::class)->set($project);

    $this->actingAs($user)
        ->get(route('settings.ai-instructions.edit'))
        ->assertInertia(fn ($page) => $page
            ->where('promptInstructions', 'Write in French.')
            ->where('linkedinPromptInstructions', 'Be punchy.'));
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
