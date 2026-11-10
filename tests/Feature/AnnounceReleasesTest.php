<?php

use App\Actions\AnnounceReleases;
use App\Enums\SocialAccountStatus;
use App\Enums\SocialPlatform;
use App\Enums\SocialPostFrequency;
use App\Jobs\GenerateSocialPost;
use App\Models\CodeRepository;
use App\Models\Project;
use App\Models\SocialAccount;
use Illuminate\Http\Client\Factory;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;

function fakeLatestRelease(int $id): void
{
    Http::swap(new Factory);
    Http::fake(['api.github.com/repos/dricle/app/releases/latest' => Http::response([
        'id' => $id,
        'name' => 'v2.0',
        'tag_name' => 'v2.0',
        'body' => '- Export leads to CSV',
        'html_url' => 'https://github.com/dricle/app/releases/tag/v2.0',
    ])]);
}

it('announces a new release once, on every network the project posts on', function () {
    Queue::fake();
    $project = Project::factory()->create([
        'x_post_frequency' => SocialPostFrequency::Weekly,
        'bluesky_post_frequency' => SocialPostFrequency::Weekly,
        'linkedin_post_frequency' => SocialPostFrequency::Weekly,
    ]);
    SocialAccount::factory()->create(['organization_id' => $project->organization_id, 'status' => SocialAccountStatus::Active])->projects()->attach($project);
    $repo = CodeRepository::factory()->for($project)->create(['url' => 'https://github.com/dricle/app', 'name' => 'app']);

    // First check: a release that predates linking the repo is only recorded.
    fakeLatestRelease(1);
    expect(app(AnnounceReleases::class)->handle())->toBe(0)
        ->and($repo->fresh()->last_release_id)->toBe(1);

    fakeLatestRelease(2);
    expect(app(AnnounceReleases::class)->handle())->toBe(2)
        ->and(app(AnnounceReleases::class)->handle())->toBe(0);

    // X needs no account, Bluesky has one, LinkedIn has none.
    Queue::assertPushed(GenerateSocialPost::class, 2);
    Queue::assertPushed(GenerateSocialPost::class, fn (GenerateSocialPost $job): bool => $job->platform === SocialPlatform::Bluesky
        && str_contains($job->brief, 'Export leads to CSV')
        && str_contains($job->brief, 'releases/tag/v2.0'));
    Queue::assertNotPushed(GenerateSocialPost::class, fn (GenerateSocialPost $job): bool => $job->platform === SocialPlatform::Linkedin);
});

it('leaves a repo without releases alone', function () {
    Queue::fake();
    Http::fake(['api.github.com/*' => Http::response(['message' => 'Not Found'], 404)]);
    $repo = CodeRepository::factory()->for(Project::factory()->create(['x_post_frequency' => SocialPostFrequency::Weekly]))->create();

    expect(app(AnnounceReleases::class)->handle())->toBe(0)
        ->and($repo->fresh()->last_release_id)->toBeNull();
    Queue::assertNothingPushed();
});
