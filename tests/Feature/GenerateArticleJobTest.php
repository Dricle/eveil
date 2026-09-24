<?php

use App\Actions\GenerateDueArticles;
use App\Ai\Agents\ArticleWriter;
use App\Enums\AnalysisStatus;
use App\Enums\AnalysisType;
use App\Enums\ArticleFrequency;
use App\Enums\ArticleSourceType;
use App\Enums\ArticleStatus;
use App\Enums\IdeaStatus;
use App\Jobs\GenerateArticle;
use App\Models\AgentRun;
use App\Models\Article;
use App\Models\Idea;
use App\Models\Project;
use App\Models\ProjectAnalysis;
use App\Models\User;
use App\Notifications\ArticleDrafted;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;
use Laravel\Ai\Responses\Data\Meta;
use Laravel\Ai\Responses\Data\Usage;
use Laravel\Ai\Responses\StructuredTextResponse;

function fakeArticleResponse(array $structured): StructuredTextResponse
{
    return new StructuredTextResponse(
        [
            'source_type' => 'feature',
            'source_ref' => '',
            'evidence' => 'The site never explains widgets.',
            'title' => 'How widgets work',
            'meta_description' => 'Everything about widgets.',
            'body' => "## Widgets\n\nThey work.",
            ...$structured,
        ],
        '{}',
        new Usage(promptTokens: 10, completionTokens: 5),
        new Meta('anthropic', 'claude-opus-5'),
    );
}

it('keeps the draft the writer produced, in the site language', function () {
    $project = Project::factory()->has(User::factory())->create(['default_language' => 'fr']);
    Notification::fake();
    ArticleWriter::fake([fakeArticleResponse([])]);

    GenerateArticle::dispatchSync($project);

    expect(Article::sole())
        ->source_type->toBe(ArticleSourceType::Feature)
        ->title->toBe('How widgets work')
        ->language->toBe('fr')
        ->status->toBe(ArticleStatus::Draft);
    Notification::assertSentTo($project->users, ArticleDrafted::class);
});

it('shows the writer what the site and past articles already cover, and the open article ideas', function () {
    $project = Project::factory()->create();
    ProjectAnalysis::factory()->create([
        'project_id' => $project->id,
        'type' => AnalysisType::Website,
        'status' => AnalysisStatus::Succeeded,
        'raw' => ['max_pages' => 5, 'pages' => [['url' => 'https://acme.test/pricing', 'title' => 'Pricing', 'chars' => 100]]],
    ]);
    Article::factory()->published()->create(['project_id' => $project->id, 'title' => 'Widgets explained']);
    Idea::factory()->create(['project_id' => $project->id, 'title' => 'Best tool for widgets?', 'angle' => 'Choosing a widget tool in 2026.']);
    Idea::factory()->create(['project_id' => $project->id, 'title' => 'Dismissed thread', 'status' => IdeaStatus::Dismissed]);
    ArticleWriter::fake([fakeArticleResponse([])]);

    GenerateArticle::dispatchSync($project);

    expect(AgentRun::sole()->input['prompt'])
        ->toContain('Pricing (https://acme.test/pricing)')
        ->toContain('Widgets explained')
        ->toContain('Best tool for widgets?')
        ->toContain('Choosing a widget tool in 2026.')
        ->not->toContain('Dismissed thread');
});

it('writes the idea the user clicked, and marks it used', function () {
    $project = Project::factory()->has(User::factory())->create();
    $idea = Idea::factory()->create(['project_id' => $project->id, 'angle' => 'Choosing a widget tool in 2026.']);
    Notification::fake();
    ArticleWriter::fake([fakeArticleResponse(['source_type' => 'feature'])]);

    GenerateArticle::dispatchSync($project, null, $idea->id);

    $article = Article::sole();

    // Where the article came from is the click, whatever the model says.
    expect($article->source_type)->toBe(ArticleSourceType::RedditThread)
        ->and($article->source_ref)->toBe($idea->source_ref)
        ->and(AgentRun::sole()->input['prompt'])->toContain('Choosing a widget tool in 2026.')
        ->and($idea->fresh()->status)->toBe(IdeaStatus::Used)
        ->and($idea->fresh()->article_id)->toBe($article->id);
    Notification::assertNothingSent();
});

it('writes nothing for an idea dismissed before the job ran', function () {
    $project = Project::factory()->create();
    $idea = Idea::factory()->create(['project_id' => $project->id, 'status' => IdeaStatus::Dismissed]);
    ArticleWriter::fake([fakeArticleResponse([])]);

    GenerateArticle::dispatchSync($project, null, $idea->id);

    expect(Article::query()->count())->toBe(0)
        ->and(AgentRun::query()->count())->toBe(0);
});

it('writes to a brief from Evie without emailing anyone', function () {
    $project = Project::factory()->has(User::factory())->create();
    Notification::fake();
    ArticleWriter::fake([fakeArticleResponse(['source_type' => 'manual'])]);

    GenerateArticle::dispatchSync($project, 'We just shipped dark mode.');

    expect(Article::sole()->source_type)->toBe(ArticleSourceType::Manual)
        ->and(AgentRun::sole()->input['prompt'])->toContain('We just shipped dark mode.');
    Notification::assertNothingSent();
});

it('keeps nothing when the writer returns no body', function () {
    $project = Project::factory()->create();
    Notification::fake();
    ArticleWriter::fake([fakeArticleResponse(['body' => ''])]);

    GenerateArticle::dispatchSync($project);

    expect(Article::query()->count())->toBe(0);
    Notification::assertNothingSent();
});

it('queues only the projects whose article cadence is due, and moves them on', function () {
    Queue::fake();
    $due = Project::factory()->create(['article_frequency' => ArticleFrequency::Weekly, 'article_next_at' => now()->subMinute()]);
    Project::factory()->create(['article_frequency' => ArticleFrequency::Weekly, 'article_next_at' => now()->addDay()]);
    Project::factory()->create(['article_frequency' => ArticleFrequency::Off, 'article_next_at' => now()->subMinute()]);

    expect(app(GenerateDueArticles::class)->handle())->toBe(1);

    Queue::assertPushed(GenerateArticle::class, fn (GenerateArticle $job): bool => $job->project->is($due));
    expect($due->fresh()->article_next_at->isAfter(now()->addDays(6)))->toBeTrue();
});
