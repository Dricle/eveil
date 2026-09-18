<?php

use App\Actions\GenerateDueRedditScans;
use App\Enums\RedditScanFrequency;
use App\Jobs\ScanRedditOpportunities;
use App\Models\Project;
use Illuminate\Support\Facades\Queue;

/**
 * Mirrors `GenerateDueLinkedinPostsTest`'s coverage: what decides a project
 * is due, and that the cadence advances regardless of what the job finds -
 * unlike the LinkedIn version, no account-status gate to test, since this
 * feature has no account at all.
 */
it('queues a scan for a project whose cadence is due', function () {
    Queue::fake();

    $project = Project::factory()->create([
        'reddit_scan_frequency' => RedditScanFrequency::Daily,
        'reddit_next_scan_at' => now()->subMinute(),
    ]);

    expect(app(GenerateDueRedditScans::class)->handle())->toBe(1);

    Queue::assertPushed(ScanRedditOpportunities::class, fn (ScanRedditOpportunities $job) => $job->project->is($project));

    expect($project->fresh()->reddit_next_scan_at)->toBeGreaterThan(now()->addHours(23));
});

it('skips a project set to off', function () {
    Queue::fake();

    $project = Project::factory()->create([
        'reddit_scan_frequency' => RedditScanFrequency::Off,
        'reddit_next_scan_at' => now()->subMinute(),
    ]);

    expect(app(GenerateDueRedditScans::class)->handle())->toBe(0);
    Queue::assertNotPushed(ScanRedditOpportunities::class);
    expect($project->fresh()->reddit_next_scan_at)->not->toBeNull();
});

it('skips a project not due yet', function () {
    Queue::fake();

    Project::factory()->create([
        'reddit_scan_frequency' => RedditScanFrequency::Weekly,
        'reddit_next_scan_at' => now()->addDays(3),
    ]);

    expect(app(GenerateDueRedditScans::class)->handle())->toBe(0);
});

it('skips a project whose next scan was never set', function () {
    Queue::fake();

    $project = Project::factory()->create([
        'reddit_scan_frequency' => RedditScanFrequency::Daily,
        'reddit_next_scan_at' => null,
    ]);

    expect(app(GenerateDueRedditScans::class)->handle())->toBe(0);
    Queue::assertNotPushed(ScanRedditOpportunities::class);
    expect($project->fresh()->reddit_next_scan_at)->toBeNull();
});
