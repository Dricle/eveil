<?php

use App\Actions\GenerateDueLinkedinPosts;
use App\Enums\LinkedinPostFrequency;
use App\Jobs\GenerateLinkedinPost;
use App\Models\LinkedinAccount;
use App\Models\Project;
use Illuminate\Support\Facades\Queue;

/**
 * Mirrors `SendingTest`'s coverage of `DispatchDueSends`: what decides a
 * project is due, and that the cadence advances regardless of what the job
 * finds, so a quiet project never gets checked again before its next tick.
 */
function linkedinReady(Project $project): LinkedinAccount
{
    $account = LinkedinAccount::factory()->create(['organization_id' => $project->organization_id]);
    $account->projects()->attach($project);

    return $account;
}

it('queues a generation for a project whose cadence is due', function () {
    Queue::fake();

    $project = Project::factory()->create([
        'linkedin_post_frequency' => LinkedinPostFrequency::Daily,
        'linkedin_next_post_at' => now()->subMinute(),
    ]);
    linkedinReady($project);

    expect(app(GenerateDueLinkedinPosts::class)->handle())->toBe(1);

    Queue::assertPushed(GenerateLinkedinPost::class, fn (GenerateLinkedinPost $job) => $job->project->is($project));

    expect($project->fresh()->linkedin_next_post_at)->toBeGreaterThan(now()->addHours(23));
});

it('skips a project set to off', function () {
    Queue::fake();

    $project = Project::factory()->create([
        'linkedin_post_frequency' => LinkedinPostFrequency::Off,
        'linkedin_next_post_at' => now()->subMinute(),
    ]);
    linkedinReady($project);

    expect(app(GenerateDueLinkedinPosts::class)->handle())->toBe(0);
    Queue::assertNotPushed(GenerateLinkedinPost::class);
});

it('skips a project not due yet', function () {
    Queue::fake();

    $project = Project::factory()->create([
        'linkedin_post_frequency' => LinkedinPostFrequency::Weekly,
        'linkedin_next_post_at' => now()->addDays(3),
    ]);
    linkedinReady($project);

    expect(app(GenerateDueLinkedinPosts::class)->handle())->toBe(0);
});

it('skips a project with no LinkedIn account connected yet, the safe default', function () {
    Queue::fake();

    $project = Project::factory()->create([
        'linkedin_post_frequency' => LinkedinPostFrequency::Daily,
        'linkedin_next_post_at' => now()->subMinute(),
    ]);

    expect(app(GenerateDueLinkedinPosts::class)->handle())->toBe(0)
        // Untouched: a project that was never due for a real reason must not
        // silently start counting down anyway.
        ->and($project->fresh()->linkedin_next_post_at)->toEqualWithDelta(now()->subMinute(), 2);
});
