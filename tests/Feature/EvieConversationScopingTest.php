<?php

use App\Ai\Agents\Evie;
use App\Models\Project;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * One conversation with Evie per project, scoped through laravel/ai's own
 * participant morph (Project as the conversation participant) rather than a
 * hand-rolled project_id column - so two projects must never share a thread,
 * and switching projects must never leak the other one's history.
 */
function storeConversationFor(Project $project, string $title): string
{
    $id = (string) Str::uuid7();

    DB::table('agent_conversations')->insert([
        'id' => $id,
        'participant_type' => Project::class,
        'participant_id' => $project->id,
        'title' => $title,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    return $id;
}

it('starts a fresh conversation with no id until one is created', function () {
    $agent = (new Evie(Project::factory()->create()))->forParticipant(Project::factory()->create());

    expect($agent->currentConversation())->toBeNull();
});

it('picks up the right project\'s last conversation, not another one\'s', function () {
    $projectA = Project::factory()->create();
    $projectB = Project::factory()->create();

    $conversationA = storeConversationFor($projectA, 'Project A chat');
    storeConversationFor($projectB, 'Project B chat');

    $agent = (new Evie($projectA))->continueLastConversation($projectA);

    expect($agent->currentConversation())->toBe($conversationA);
});

it('starts clean for a project with no prior conversation', function () {
    $projectWithHistory = Project::factory()->create();
    $freshProject = Project::factory()->create();

    storeConversationFor($projectWithHistory, 'Old chat');

    $agent = (new Evie($freshProject))->continueLastConversation($freshProject);

    expect($agent->currentConversation())->toBeNull();
});

it('resumes the exact conversation id given, regardless of which project last touched it', function () {
    $project = Project::factory()->create();
    $conversationId = storeConversationFor($project, 'Specific thread');

    $agent = (new Evie($project))->continue($conversationId, $project);

    expect($agent->currentConversation())->toBe($conversationId);
});
