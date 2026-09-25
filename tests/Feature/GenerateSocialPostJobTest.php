<?php

use App\Ai\Agents\SocialPostWriter;
use App\Enums\ArticleStatus;
use App\Enums\AutonomyLevel;
use App\Enums\OrganizationRole;
use App\Enums\OutreachStatus;
use App\Enums\SocialPlatform;
use App\Enums\SocialPostSourceType;
use App\Enums\SocialPostStatus;
use App\Jobs\GenerateSocialPost;
use App\Models\AgentRun;
use App\Models\Article;
use App\Models\Company;
use App\Models\Project;
use App\Models\SocialAccount;
use App\Models\SocialPost;
use App\Models\SocialPostExample;
use App\Models\User;
use App\Notifications\SocialPostDrafted;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Laravel\Ai\Responses\Data\Meta;
use Laravel\Ai\Responses\Data\Usage;
use Laravel\Ai\Responses\StructuredTextResponse;

function fakeSocialWriter(array $structured): void
{
    SocialPostWriter::fake([new StructuredTextResponse(
        $structured,
        '{}',
        new Usage(promptTokens: 10, completionTokens: 5),
        new Meta('anthropic', 'claude-opus-5'),
    )]);
}

beforeEach(function () {
    Notification::fake();
});

it('keeps a draft for the network it was asked for, and emails about it', function () {
    $project = Project::factory()->create();
    $owner = User::factory()->create();
    $project->organization->users()->attach($owner, ['role' => OrganizationRole::Owner->value]);
    fakeSocialWriter(['source_type' => 'knowledge_base', 'evidence' => 'Widgets are a key feature.', 'body' => 'We build widgets. https://acme.test']);

    GenerateSocialPost::dispatchSync($project, SocialPlatform::X);

    expect(SocialPost::sole())
        ->platform->toBe(SocialPlatform::X)
        ->source_type->toBe(SocialPostSourceType::KnowledgeBase)
        ->status->toBe(SocialPostStatus::Draft)
        ->body->toBe('We build widgets. https://acme.test');

    expect(AgentRun::sole()->input['prompt'])->toContain($project->url);
    Notification::assertSentTo($owner, SocialPostDrafted::class);
});

it('points a client win at the company and never proposes it twice on that network', function () {
    $project = Project::factory()->create();
    $company = Company::factory()->create(['project_id' => $project->id, 'status' => OutreachStatus::Won, 'name' => 'Acme Corp']);
    fakeSocialWriter(['source_type' => 'client_won', 'evidence' => 'A new client.', 'body' => 'New client in logistics.']);

    GenerateSocialPost::dispatchSync($project, SocialPlatform::Bluesky);

    expect(SocialPost::sole()->source_ref)->toBe((string) $company->id)
        // Never named: a client win goes out anonymized on these networks.
        ->and(AgentRun::sole()->input['prompt'])->not->toContain('Acme Corp');

    fakeSocialWriter(['source_type' => 'knowledge_base', 'evidence' => 'e', 'body' => 'A fact.']);

    GenerateSocialPost::dispatchSync($project, SocialPlatform::Bluesky);

    expect(AgentRun::query()->latest('id')->first()->input['prompt'])->not->toContain('Pending client win');
});

it('offers a freshly published article not shared on that network yet', function () {
    $project = Project::factory()->create();
    $article = Article::factory()->create([
        'project_id' => $project->id,
        'status' => ArticleStatus::Published,
        'title' => 'How to pick a widget',
        'published_url' => 'https://acme.test/blog/widgets',
        'published_at' => now()->subDay(),
    ]);
    fakeSocialWriter(['source_type' => 'article', 'evidence' => 'New article.', 'body' => 'Read it: https://acme.test/blog/widgets']);

    GenerateSocialPost::dispatchSync($project, SocialPlatform::X);

    expect(AgentRun::sole()->input['prompt'])->toContain('https://acme.test/blog/widgets')
        ->and(SocialPost::sole()->source_ref)->toBe((string) $article->id);
});

it('publishes straight away under autonomous Bluesky, with nobody to notify', function () {
    $project = Project::factory()->create(['bluesky_autonomy_level' => AutonomyLevel::Autonomous]);
    SocialAccount::factory()->create(['organization_id' => $project->organization_id])->projects()->attach($project);
    Http::fake([
        'bsky.social/xrpc/com.atproto.server.createSession' => Http::response(['accessJwt' => 'jwt', 'did' => 'did:plc:a', 'handle' => 'a.bsky.social']),
        'bsky.social/xrpc/com.atproto.repo.createRecord' => Http::response(['uri' => 'at://did:plc:a/app.bsky.feed.post/1']),
        '*' => Http::response('', 404),
    ]);
    fakeSocialWriter(['source_type' => 'knowledge_base', 'evidence' => 'e', 'body' => 'A fact.']);

    GenerateSocialPost::dispatchSync($project, SocialPlatform::Bluesky);

    expect(SocialPost::sole()->status)->toBe(SocialPostStatus::Published);
    Notification::assertNothingSent();
});

it('never publishes an X post on its own, whatever the Bluesky setting says', function () {
    $project = Project::factory()->create(['bluesky_autonomy_level' => AutonomyLevel::Autonomous]);
    SocialAccount::factory()->create(['organization_id' => $project->organization_id])->projects()->attach($project);
    Http::fake();
    fakeSocialWriter(['source_type' => 'knowledge_base', 'evidence' => 'e', 'body' => 'A fact.']);

    GenerateSocialPost::dispatchSync($project, SocialPlatform::X);

    expect(SocialPost::sole()->status)->toBe(SocialPostStatus::Draft);
    Http::assertNothingSent();
});

it('feeds only that network\'s own rejections into the prompt', function () {
    $project = Project::factory()->create();
    SocialPost::factory()->create(['project_id' => $project->id, 'status' => SocialPostStatus::Rejected, 'body' => 'Bluesky flop', 'rejection_reason' => 'Too long']);
    SocialPost::factory()->x()->create(['project_id' => $project->id, 'status' => SocialPostStatus::Rejected, 'body' => 'X flop']);
    fakeSocialWriter(['source_type' => 'knowledge_base', 'evidence' => 'e', 'body' => 'A fact.']);

    GenerateSocialPost::dispatchSync($project, SocialPlatform::Bluesky);

    expect(AgentRun::sole()->input['prompt'])->toContain('Bluesky flop')->toContain('Too long')->not->toContain('X flop');
});

it('feeds that network\'s shared bank into the prompt, never the other one', function () {
    $project = Project::factory()->create();
    SocialPostExample::factory()->create(['platform' => SocialPlatform::Bluesky, 'body' => 'Bluesky classic']);
    SocialPostExample::factory()->create(['platform' => SocialPlatform::X, 'body' => 'X classic']);
    fakeSocialWriter(['source_type' => 'knowledge_base', 'evidence' => 'e', 'body' => 'A fact.']);

    GenerateSocialPost::dispatchSync($project, SocialPlatform::Bluesky);

    expect(AgentRun::sole()->input['prompt'])->toContain('Bluesky classic')->not->toContain('X classic');
});
