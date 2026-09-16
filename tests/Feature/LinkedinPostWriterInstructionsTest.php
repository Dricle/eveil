<?php

use App\Ai\Agents\LinkedinPostWriter;
use App\Models\Project;

/**
 * `instructions()` never reads the injected evidence, only the project's
 * tone boxes - so every test here passes empty collections.
 */
function linkedinWriter(Project $project): LinkedinPostWriter
{
    return new LinkedinPostWriter($project, null, collect(), collect(), collect(), collect(), '');
}

it('never inherits the project\'s email-writing instructions', function () {
    $project = Project::factory()->create(['prompt_instructions' => 'Write in French. Never use emoji.']);

    expect((string) linkedinWriter($project)->instructions())
        ->not->toContain('Write in French. Never use emoji.');
});

it('carries the project\'s own LinkedIn-specific tone, and only that box', function () {
    $project = Project::factory()->create([
        'prompt_instructions' => 'Write in French.',
        'linkedin_prompt_instructions' => 'Be punchy, first person, one idea per line.',
    ]);

    $instructions = (string) linkedinWriter($project)->instructions();

    expect($instructions)
        ->toContain('Be punchy, first person, one idea per line.')
        ->toEndWith('Be punchy, first person, one idea per line.')
        ->not->toContain('Write in French.');
});

it('adds nothing when the project has no LinkedIn-specific instructions', function () {
    $project = Project::factory()->create(['linkedin_prompt_instructions' => null]);

    expect((string) linkedinWriter($project)->instructions())
        ->not->toContain("user's own instructions");
});
