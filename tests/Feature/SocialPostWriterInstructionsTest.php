<?php

use App\Ai\Agents\SocialPostWriter;
use App\Enums\SocialPlatform;
use App\Models\Project;

/**
 * `instructions()` never reads the injected evidence, only the project's
 * tone boxes - so every test here passes empty collections.
 */
function postWriter(Project $project, SocialPlatform $platform): SocialPostWriter
{
    return new SocialPostWriter($project, $platform, null, collect(), null, collect(), collect(), collect(), '');
}

it('never inherits the project\'s email-writing instructions', function () {
    $project = Project::factory()->create(['prompt_instructions' => 'Write in French. Never use emoji.']);

    expect((string) postWriter($project, SocialPlatform::Linkedin)->instructions())
        ->not->toContain('Write in French. Never use emoji.');
});

it('carries the network\'s own tone box, and only that one', function () {
    $project = Project::factory()->create([
        'prompt_instructions' => 'Write in French.',
        'linkedin_prompt_instructions' => 'Be punchy, first person, one idea per line.',
        'x_prompt_instructions' => 'No hashtags on X.',
    ]);

    expect((string) postWriter($project, SocialPlatform::Linkedin)->instructions())
        ->toEndWith('Be punchy, first person, one idea per line.')
        ->not->toContain('Write in French.')
        ->not->toContain('No hashtags on X.')
        ->and((string) postWriter($project, SocialPlatform::X)->instructions())
        ->toEndWith('No hashtags on X.')
        ->not->toContain('Be punchy');
});

it('adds nothing when the network has no tone box filled', function () {
    $project = Project::factory()->create(['bluesky_prompt_instructions' => null]);

    expect((string) postWriter($project, SocialPlatform::Bluesky)->instructions())
        ->not->toContain("user's own instructions");
});

it('tells each network its own length and form', function () {
    $project = Project::factory()->create();

    expect((string) postWriter($project, SocialPlatform::X)->instructions())->toContain('280 characters')
        ->and((string) postWriter($project, SocialPlatform::Bluesky)->instructions())->toContain('300 characters')
        ->and((string) postWriter($project, SocialPlatform::Linkedin)->instructions())->toContain('LinkedIn style');
});
