<?php

use App\Ai\Tools\GetSocialPost;
use App\Models\Project;
use App\Models\SocialPost;
use Laravel\Ai\Tools\Request;

it('reads a post older than the ten listed', function () {
    $project = Project::factory()->create();
    $old = SocialPost::factory()->create(['project_id' => $project->id, 'body' => 'An older draft']);
    SocialPost::factory()->count(10)->create(['project_id' => $project->id]);

    $result = (string) (new GetSocialPost($project))->handle(new Request(['social_post_id' => $old->id]));

    expect($result)->toContain('An older draft');
});

it('never reads another project\'s post', function () {
    $project = Project::factory()->create();
    $foreign = SocialPost::factory()->create(['body' => 'Foreign post']);

    $result = (string) (new GetSocialPost($project))->handle(new Request(['social_post_id' => $foreign->id]));

    expect($result)->toContain('No post')
        ->not->toContain('Foreign post');
});
