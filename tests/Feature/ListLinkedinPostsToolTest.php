<?php

use App\Ai\Tools\ListLinkedinPosts;
use App\Models\LinkedinPost;
use App\Models\Project;
use Laravel\Ai\Tools\Request;

it('lists only this project\'s posts, most recent first', function () {
    $project = Project::factory()->create();
    $older = LinkedinPost::factory()->create(['project_id' => $project->id, 'body' => 'Older.']);
    $newer = LinkedinPost::factory()->create(['project_id' => $project->id, 'body' => 'Newer.']);
    LinkedinPost::factory()->for(Project::factory())->create(['body' => 'Someone else\'s.']);

    $result = json_decode((string) (new ListLinkedinPosts($project))->handle(new Request([])), true);

    expect($result)->toHaveCount(2)
        ->and($result[0]['id'])->toBe($newer->id)
        ->and($result[1]['id'])->toBe($older->id);
});

it('says plainly when there is nothing yet', function () {
    $project = Project::factory()->create();

    $result = (new ListLinkedinPosts($project))->handle(new Request([]));

    expect((string) $result)->toContain('no LinkedIn posts');
});
