<?php

use App\Ai\Tools\DraftLinkedinPost;
use App\Enums\LinkedinPostSourceType;
use App\Enums\LinkedinPostStatus;
use App\Models\LinkedinPost;
use App\Models\Project;
use Laravel\Ai\Tools\Request;

it('drafts a post without publishing it', function () {
    $project = Project::factory()->create();

    $result = (new DraftLinkedinPost($project))->handle(new Request([
        'body' => 'We just shipped dark mode.',
        'evidence' => 'User told Evie about the new feature.',
    ]));

    $post = LinkedinPost::sole();

    expect((string) $result)->toContain('queue')
        ->and($post->source_type)->toBe(LinkedinPostSourceType::Manual)
        ->and($post->status)->toBe(LinkedinPostStatus::Draft)
        ->and($post->body)->toBe('We just shipped dark mode.')
        ->and($post->urn)->toBeNull();
});
