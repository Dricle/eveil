<?php

use App\Ai\Agents\SocialPostWriter;
use App\Enums\AutonomyLevel;
use App\Enums\OutreachStatus;
use App\Enums\SocialPlatform;
use App\Enums\SocialPostSourceType;
use App\Enums\SocialPostStatus;
use App\Enums\SocialPostVariant;
use App\Jobs\GenerateSocialPost;
use App\Models\AgentRun;
use App\Models\Company;
use App\Models\Project;
use App\Models\SocialAccount;
use App\Models\SocialPost;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Laravel\Ai\Responses\Data\Meta;
use Laravel\Ai\Responses\Data\TextUsage;
use Laravel\Ai\Responses\StructuredTextResponse;

/**
 * LinkedIn on the shared post pipeline, and the client-win pairs every
 * network now writes.
 */
function fakeLinkedinWriter(array $structured): void
{
    SocialPostWriter::fake([new StructuredTextResponse(
        $structured,
        '{}',
        new TextUsage(inputTokens: 10, outputTokens: 5),
        new Meta('anthropic', 'claude-opus-5'),
    )]);
}

function clientWinDraft(): array
{
    return [
        'source_type' => 'client_won',
        'evidence' => 'Acme just signed.',
        'body_named' => 'We just started working with Acme.',
        'body_anonymized' => 'We just onboarded a new client in logistics.',
    ];
}

function autonomousLinkedinProject(int $accounts = 1): Project
{
    $project = Project::factory()->create(['linkedin_autonomy_level' => AutonomyLevel::Autonomous]);

    SocialAccount::factory()->linkedin()->count($accounts)->create(['organization_id' => $project->organization_id])
        ->each(fn (SocialAccount $account) => $account->projects()->attach($project));

    Http::fake(['api.linkedin.com/rest/posts' => Http::response('', 201, ['x-restli-id' => 'urn:li:share:9'])]);

    return $project;
}

beforeEach(function () {
    Notification::fake();
});

it('writes a named and an anonymized sibling for a client win, both drafts', function () {
    $project = Project::factory()->create();
    $company = Company::factory()->create(['project_id' => $project->id, 'status' => OutreachStatus::Won, 'name' => 'Acme']);
    fakeLinkedinWriter(clientWinDraft());

    GenerateSocialPost::dispatchSync($project, SocialPlatform::Linkedin);

    $posts = SocialPost::query()->get();

    expect($posts)->toHaveCount(2)
        ->and($posts->firstWhere('variant', SocialPostVariant::Named)->body)->toBe('We just started working with Acme.')
        ->and($posts->firstWhere('variant', SocialPostVariant::Anonymized)->body)->toBe('We just onboarded a new client in logistics.')
        ->and($posts->pluck('source_ref')->unique()->sole())->toBe((string) $company->id)
        ->and($posts->pluck('status')->unique()->sole())->toBe(SocialPostStatus::Draft)
        ->and(AgentRun::sole()->input['prompt'])->toContain('Company: Acme');
});

it('never proposes the same client win twice on that network', function () {
    $project = Project::factory()->create();
    $company = Company::factory()->create(['project_id' => $project->id, 'status' => OutreachStatus::Won]);
    SocialPost::factory()->linkedin()->create([
        'project_id' => $project->id,
        'source_type' => SocialPostSourceType::ClientWon,
        'source_ref' => (string) $company->id,
    ]);
    fakeLinkedinWriter(['source_type' => 'knowledge_base', 'evidence' => 'e', 'body' => 'A fact.']);

    GenerateSocialPost::dispatchSync($project, SocialPlatform::Linkedin);

    expect(AgentRun::sole()->input['prompt'])->not->toContain('Pending client win');
});

it('feeds this project\'s own promoted posts on that network into the prompt', function () {
    $project = Project::factory()->create();
    SocialPost::factory()->linkedin()->create(['project_id' => $project->id, 'status' => SocialPostStatus::Published, 'body' => 'A post that landed.', 'promoted_at' => now()]);
    fakeLinkedinWriter(['source_type' => 'knowledge_base', 'evidence' => 'e', 'body' => 'A fact.']);

    GenerateSocialPost::dispatchSync($project, SocialPlatform::Linkedin);

    expect(AgentRun::sole()->input['prompt'])->toContain('A post that landed.');
});

it('does not notify anyone when nothing was drafted', function () {
    $project = Project::factory()->create();
    fakeLinkedinWriter(['source_type' => 'knowledge_base', 'evidence' => 'e', 'body' => '']);

    GenerateSocialPost::dispatchSync($project, SocialPlatform::Linkedin);

    expect(SocialPost::count())->toBe(0);
    Notification::assertNothingSent();
});

it('publishes straight away under autonomous LinkedIn, with nobody to notify', function () {
    $project = autonomousLinkedinProject();
    fakeLinkedinWriter(['source_type' => 'knowledge_base', 'evidence' => 'e', 'body' => 'A fact.']);

    GenerateSocialPost::dispatchSync($project, SocialPlatform::Linkedin);

    expect(SocialPost::sole())
        ->status->toBe(SocialPostStatus::Published)
        ->external_id->toBe('urn:li:share:9')
        ->url->toBe('https://www.linkedin.com/feed/update/urn:li:share:9/');
    Notification::assertNothingSent();
});

it('only ever auto-publishes the anonymized side of a client win', function () {
    $project = autonomousLinkedinProject();
    Company::factory()->create(['project_id' => $project->id, 'status' => OutreachStatus::Won]);
    fakeLinkedinWriter(clientWinDraft());

    GenerateSocialPost::dispatchSync($project, SocialPlatform::Linkedin);

    expect(SocialPost::query()->where('variant', SocialPostVariant::Anonymized)->sole()->status)->toBe(SocialPostStatus::Published)
        ->and(SocialPost::query()->where('variant', SocialPostVariant::Named)->sole())
        ->status->toBe(SocialPostStatus::Rejected)
        ->rejection_reason->toBe('Superseded by the other variant.');
});

it('leaves a draft for a person when there is more than one account to post as', function () {
    $project = autonomousLinkedinProject(accounts: 2);
    fakeLinkedinWriter(['source_type' => 'knowledge_base', 'evidence' => 'e', 'body' => 'A fact.']);

    GenerateSocialPost::dispatchSync($project, SocialPlatform::Linkedin);

    expect(SocialPost::sole()->status)->toBe(SocialPostStatus::Draft);
    Http::assertNothingSent();
});
