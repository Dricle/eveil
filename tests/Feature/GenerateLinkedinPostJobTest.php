<?php

use App\Ai\Agents\LinkedinPostWriter;
use App\Enums\LinkedinPostSourceType;
use App\Enums\LinkedinPostVariant;
use App\Enums\OutreachStatus;
use App\Jobs\GenerateLinkedinPost;
use App\Models\Company;
use App\Models\LinkedinPost;
use App\Models\Project;
use Laravel\Ai\Responses\Data\Meta;
use Laravel\Ai\Responses\Data\Usage;
use Laravel\Ai\Responses\StructuredTextResponse;

function fakeWriterResponse(array $structured): StructuredTextResponse
{
    return new StructuredTextResponse(
        $structured,
        '{}',
        new Usage(promptTokens: 10, completionTokens: 5),
        new Meta('anthropic', 'claude-opus-5'),
    );
}

it('persists a single draft from a knowledge base post', function () {
    $project = Project::factory()->create(['knowledge_base' => ['key_features' => ['Widgets']]]);

    LinkedinPostWriter::fake([fakeWriterResponse([
        'source_type' => 'knowledge_base',
        'evidence' => 'The site names "Widgets" as a key feature.',
        'body' => 'We build widgets.',
    ])]);

    GenerateLinkedinPost::dispatchSync($project);

    $post = LinkedinPost::sole();

    expect($post->source_type)->toBe(LinkedinPostSourceType::KnowledgeBase)
        ->and($post->variant)->toBeNull()
        ->and($post->body)->toBe('We build widgets.')
        ->and($post->status->value)->toBe('draft');
});

it('writes a named and an anonymized sibling for a client win, both drafts', function () {
    $project = Project::factory()->create();
    $company = Company::factory()->create(['project_id' => $project->id, 'status' => OutreachStatus::Won, 'name' => 'Acme']);

    LinkedinPostWriter::fake([fakeWriterResponse([
        'source_type' => 'client_won',
        'evidence' => 'Acme just signed.',
        'body_named' => 'We just started working with Acme.',
        'body_anonymized' => 'We just onboarded a new client.',
    ])]);

    GenerateLinkedinPost::dispatchSync($project);

    $posts = LinkedinPost::query()->orderBy('variant')->get();

    expect($posts)->toHaveCount(2)
        ->and($posts->firstWhere('variant', LinkedinPostVariant::Anonymized)->body)->toBe('We just onboarded a new client.')
        ->and($posts->firstWhere('variant', LinkedinPostVariant::Named)->body)->toBe('We just started working with Acme.')
        ->and($posts->pluck('source_ref')->unique()->sole())->toBe((string) $company->id);
});

it('never proposes the same client win twice', function () {
    $project = Project::factory()->create();
    $company = Company::factory()->create(['project_id' => $project->id, 'status' => OutreachStatus::Won]);

    LinkedinPost::factory()->create([
        'project_id' => $project->id,
        'source_type' => LinkedinPostSourceType::ClientWon,
        'source_ref' => (string) $company->id,
    ]);

    LinkedinPostWriter::fake([fakeWriterResponse([
        'source_type' => 'knowledge_base',
        'evidence' => 'Fallback, since the win was already covered.',
        'body' => 'A knowledge base fact.',
    ])]);

    GenerateLinkedinPost::dispatchSync($project);

    expect(LinkedinPost::where('source_type', LinkedinPostSourceType::ClientWon)->count())->toBe(1);
});
