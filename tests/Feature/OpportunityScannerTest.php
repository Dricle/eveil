<?php

use App\Models\Project;
use App\Models\RedditReply;
use App\Models\TargetProfile;
use App\Services\Reddit\OpportunityScanner;
use App\Support\CurrentProject;
use Illuminate\Support\Facades\Http;

function projectTrackingSubreddit(string $name = 'selfhosted'): Project
{
    $project = Project::factory()->create();
    TargetProfile::factory()->create([
        'project_id' => $project->id,
        'criteria' => ['subreddits' => [['name' => $name, 'subscribers' => 10_000, 'description' => '']]],
    ]);

    return $project;
}

it('finds nothing for a project with no tracked subreddits', function () {
    $project = Project::factory()->create();
    Http::fake();

    expect(app(OpportunityScanner::class)->find($project))->toBeEmpty();
    Http::assertNothingSent();
});

it('drops removed/deleted posts and comments before they ever reach triage', function () {
    $project = projectTrackingSubreddit();

    Http::fake([
        'arctic-shift.photon-reddit.com/api/posts/search*' => Http::response(['data' => [
            ['id' => '1', 'title' => '[ Removed by moderator ]', 'selftext' => '', 'author' => 'someone', 'subreddit' => 'selfhosted', 'permalink' => '/r/selfhosted/comments/1/x/'],
            ['id' => '2', 'title' => 'A real question', 'selftext' => 'Anyone tried this?', 'author' => 'real_user', 'subreddit' => 'selfhosted', 'permalink' => '/r/selfhosted/comments/2/y/'],
        ]]),
        'arctic-shift.photon-reddit.com/api/comments/search*' => Http::response(['data' => [
            ['body' => '[deleted]', 'author' => '[deleted]', 'link_id' => 't3_3', 'subreddit' => 'selfhosted', 'permalink' => '/r/selfhosted/comments/3/z/c1/'],
            ['body' => 'A real comment with content.', 'author' => 'real_commenter', 'link_id' => 't3_4', 'subreddit' => 'selfhosted', 'permalink' => '/r/selfhosted/comments/4/w/c2/'],
        ]]),
    ]);

    $candidates = app(OpportunityScanner::class)->find($project);

    expect($candidates->pluck('permalink')->all())->toBe([
        'https://www.reddit.com/r/selfhosted/comments/2/y/',
        'https://www.reddit.com/r/selfhosted/comments/4/w/c2/',
    ]);
});

it('never re-scans a permalink that already has a reply row in this project', function () {
    $project = projectTrackingSubreddit();
    app(CurrentProject::class)->set($project);
    RedditReply::factory()->create([
        'project_id' => $project->id,
        'thread_permalink' => 'https://www.reddit.com/r/selfhosted/comments/2/y/',
    ]);

    Http::fake([
        'arctic-shift.photon-reddit.com/api/posts/search*' => Http::response(['data' => [
            ['id' => '2', 'title' => 'A real question', 'selftext' => '', 'author' => 'real_user', 'subreddit' => 'selfhosted', 'permalink' => '/r/selfhosted/comments/2/y/'],
        ]]),
        'arctic-shift.photon-reddit.com/api/comments/search*' => Http::response(['data' => []]),
    ]);

    expect(app(OpportunityScanner::class)->find($project))->toBeEmpty();
});
