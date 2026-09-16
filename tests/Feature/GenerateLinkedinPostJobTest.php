<?php

use App\Ai\Agents\LinkedinPostWriter;
use App\Enums\LinkedinPostSourceType;
use App\Enums\LinkedinPostStatus;
use App\Enums\LinkedinPostVariant;
use App\Enums\OutreachStatus;
use App\Jobs\GenerateLinkedinPost;
use App\Models\AgentRun;
use App\Models\Company;
use App\Models\LinkedinPost;
use App\Models\LinkedinPostExample;
use App\Models\Project;
use App\Models\User;
use App\Notifications\LinkedinPostDrafted;
use Illuminate\Support\Facades\Notification;
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

it('feeds recently rejected posts and their reasons into the prompt', function () {
    $project = Project::factory()->create();
    LinkedinPost::factory()->create([
        'project_id' => $project->id,
        'status' => LinkedinPostStatus::Rejected,
        'body' => 'Too salesy.',
        'rejection_reason' => 'Sounded like an ad.',
    ]);

    LinkedinPostWriter::fake([fakeWriterResponse([
        'source_type' => 'knowledge_base',
        'evidence' => 'e',
        'body' => 'A fact.',
    ])]);

    GenerateLinkedinPost::dispatchSync($project);

    expect(AgentRun::sole()->input['prompt'])
        ->toContain('Recently rejected')
        ->toContain('Too salesy.')
        ->toContain('Sounded like an ad.');
});

it('feeds this project\'s own promoted posts into the prompt', function () {
    $project = Project::factory()->create();
    LinkedinPost::factory()->create([
        'project_id' => $project->id,
        'status' => LinkedinPostStatus::Published,
        'body' => 'Our best post ever.',
        'promoted_at' => now(),
    ]);

    LinkedinPostWriter::fake([fakeWriterResponse([
        'source_type' => 'knowledge_base',
        'evidence' => 'e',
        'body' => 'A fact.',
    ])]);

    GenerateLinkedinPost::dispatchSync($project);

    expect(AgentRun::sole()->input['prompt'])
        ->toContain('performed well')
        ->toContain('Our best post ever.');
});

it('feeds the shared instance-wide pool into the prompt', function () {
    $project = Project::factory()->create();
    LinkedinPostExample::factory()->create(['body' => 'A proven post from another tenant.']);

    LinkedinPostWriter::fake([fakeWriterResponse([
        'source_type' => 'knowledge_base',
        'evidence' => 'e',
        'body' => 'A fact.',
    ])]);

    GenerateLinkedinPost::dispatchSync($project);

    expect(AgentRun::sole()->input['prompt'])
        ->toContain('Proven LinkedIn posts')
        ->toContain('A proven post from another tenant.');
});

it('notifies the project\'s users when a draft is created', function () {
    $project = Project::factory()->has(User::factory())->create();
    Notification::fake();

    LinkedinPostWriter::fake([fakeWriterResponse([
        'source_type' => 'knowledge_base',
        'evidence' => 'e',
        'body' => 'A fact.',
    ])]);

    GenerateLinkedinPost::dispatchSync($project);

    Notification::assertSentTo($project->users, LinkedinPostDrafted::class);
});

it('does not notify anyone when nothing was drafted', function () {
    $project = Project::factory()->has(User::factory())->create();
    Notification::fake();

    LinkedinPostWriter::fake([fakeWriterResponse([
        'source_type' => 'knowledge_base',
        'evidence' => 'e',
        'body' => '',
    ])]);

    GenerateLinkedinPost::dispatchSync($project);

    Notification::assertNothingSent();
});
