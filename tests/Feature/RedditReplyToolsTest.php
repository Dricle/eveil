<?php

use App\Ai\Tools\GetRedditReply;
use App\Ai\Tools\UpdateRedditReply;
use App\Enums\RedditReplyStatus;
use App\Models\Project;
use App\Models\RedditReply;
use Illuminate\Support\Facades\Http;
use Laravel\Ai\Tools\Request;

it('rewrites a reddit reply draft', function () {
    $project = Project::factory()->create();
    $reply = RedditReply::factory()->create(['project_id' => $project->id]);

    (new UpdateRedditReply($project))->handle(new Request(['reddit_reply_id' => $reply->id, 'body' => 'Shorter reply']));

    expect($reply->fresh()->body)->toBe('Shorter reply');
});

it('refuses to rewrite a reply already posted', function () {
    $project = Project::factory()->create();
    $reply = RedditReply::factory()->create(['project_id' => $project->id, 'status' => RedditReplyStatus::Published, 'body' => 'Live reply']);

    $result = (new UpdateRedditReply($project))->handle(new Request(['reddit_reply_id' => $reply->id, 'body' => 'Rewritten']));

    expect((string) $result)->toContain('published')
        ->and($reply->fresh()->body)->toBe('Live reply');
});

it('never touches another project\'s reply', function () {
    $project = Project::factory()->create();
    $foreign = RedditReply::factory()->create(['body' => 'Foreign reply']);

    $read = (new GetRedditReply($project))->handle(new Request(['reddit_reply_id' => $foreign->id]));
    $write = (new UpdateRedditReply($project))->handle(new Request(['reddit_reply_id' => $foreign->id, 'body' => 'Hijacked']));

    expect((string) $read)->toContain('No Reddit reply')
        ->and((string) $write)->toContain('No Reddit reply')
        ->and($foreign->fresh()->body)->toBe('Foreign reply');
});

it('shows the reddit post a reply answers', function () {
    Http::fake([
        '*/api/posts/search*' => Http::response(['data' => [['id' => 'abc', 'title' => 'Best CRM?', 'selftext' => 'Looking for a CRM for my agency.']]]),
        '*/api/comments/search*' => Http::response(['data' => [['body' => 'HubSpot is fine', 'score' => 3, 'author' => 'someone']]]),
    ]);
    $project = Project::factory()->create();
    $reply = RedditReply::factory()->create(['project_id' => $project->id]);

    $result = (string) (new GetRedditReply($project))->handle(new Request(['reddit_reply_id' => $reply->id]));

    expect($result)->toContain('Looking for a CRM for my agency.')
        ->toContain('HubSpot is fine');
});
