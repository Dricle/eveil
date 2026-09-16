<?php

use App\Ai\Tools\UpdateLinkedinPost;
use App\Enums\LinkedinPostStatus;
use App\Models\LinkedinPost;
use App\Models\Project;
use Laravel\Ai\Tools\Request;

it('rewrites an existing draft in place instead of creating a second one', function () {
    $project = Project::factory()->create();
    $post = LinkedinPost::factory()->create([
        'project_id' => $project->id,
        'body' => 'Original text.',
        'status' => LinkedinPostStatus::Draft,
    ]);

    $result = (new UpdateLinkedinPost($project))->handle(new Request([
        'linkedin_post_id' => $post->id,
        'body' => 'Corrected text.',
    ]));

    expect((string) $result)->toContain((string) $post->id)
        ->and($post->fresh()->body)->toBe('Corrected text.')
        ->and(LinkedinPost::count())->toBe(1);
});

it('refuses to update a post that is no longer a draft', function () {
    $project = Project::factory()->create();
    $post = LinkedinPost::factory()->create([
        'project_id' => $project->id,
        'body' => 'Already approved.',
        'status' => LinkedinPostStatus::Approved,
    ]);

    $result = (new UpdateLinkedinPost($project))->handle(new Request([
        'linkedin_post_id' => $post->id,
        'body' => 'Too late.',
    ]));

    expect((string) $result)->toContain('already approved')
        ->and($post->fresh()->body)->toBe('Already approved.');
});

it('refuses to reach another project\'s post', function () {
    $project = Project::factory()->create();
    $theirs = LinkedinPost::factory()->for(Project::factory())->create(['body' => 'Not yours.']);

    $result = (new UpdateLinkedinPost($project))->handle(new Request([
        'linkedin_post_id' => $theirs->id,
        'body' => 'Hijacked.',
    ]));

    expect((string) $result)->toContain('ListLinkedinPosts')
        ->and($theirs->fresh()->body)->toBe('Not yours.');
});
