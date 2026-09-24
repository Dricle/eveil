<?php

use App\Ai\Tools\DismissArticleIdea;
use App\Ai\Tools\DraftArticle;
use App\Ai\Tools\GetArticle;
use App\Ai\Tools\ListArticleIdeas;
use App\Ai\Tools\UpdateArticle;
use App\Enums\IdeaStatus;
use App\Jobs\GenerateArticle;
use App\Models\Article;
use App\Models\Idea;
use App\Models\Project;
use Illuminate\Support\Facades\Queue;
use Laravel\Ai\Tools\Request;

it('queues an article from the user\'s brief rather than writing it in the chat', function () {
    Queue::fake();
    $project = Project::factory()->create();

    (new DraftArticle($project))->handle(new Request(['brief' => 'We just shipped dark mode.']));

    Queue::assertPushed(GenerateArticle::class, fn (GenerateArticle $job): bool => $job->project->is($project) && $job->brief === 'We just shipped dark mode.');
});

it('rewrites only what the user asked to change on a draft', function () {
    $project = Project::factory()->create();
    $article = Article::factory()->create(['project_id' => $project->id, 'title' => 'Old title']);

    (new UpdateArticle($project))->handle(new Request(['article_id' => $article->id, 'body' => 'Rewritten body']));

    expect($article->fresh())
        ->title->toBe('Old title')
        ->body->toBe('Rewritten body');
});

it('refuses to rewrite an article already live', function () {
    $project = Project::factory()->create();
    $article = Article::factory()->published()->create(['project_id' => $project->id, 'body' => 'Live body']);

    $result = (new UpdateArticle($project))->handle(new Request(['article_id' => $article->id, 'body' => 'Rewritten']));

    expect((string) $result)->toContain('published')
        ->and($article->fresh()->body)->toBe('Live body');
});

it('never reads another project\'s article', function () {
    $project = Project::factory()->create();
    $foreign = Article::factory()->create();

    $result = (new GetArticle($project))->handle(new Request(['article_id' => $foreign->id]));

    expect((string) $result)->toContain('No article');
});

it('lists only this project\'s open article ideas', function () {
    $project = Project::factory()->create();
    Idea::factory()->create(['project_id' => $project->id, 'angle' => 'Open angle']);
    Idea::factory()->create(['project_id' => $project->id, 'angle' => 'Dismissed angle', 'status' => IdeaStatus::Dismissed]);
    Idea::factory()->create(['angle' => 'Foreign angle']);

    $result = (string) (new ListArticleIdeas($project))->handle(new Request([]));

    expect($result)->toContain('Open angle')
        ->not->toContain('Dismissed angle')
        ->not->toContain('Foreign angle');
});

it('writes an open idea when given its id', function () {
    Queue::fake();
    $project = Project::factory()->create();
    $idea = Idea::factory()->create(['project_id' => $project->id]);

    (new DraftArticle($project))->handle(new Request(['idea_id' => $idea->id]));

    Queue::assertPushed(GenerateArticle::class, fn (GenerateArticle $job): bool => $job->ideaId === $idea->id && $job->brief === null);
});

it('refuses to write another project\'s idea', function () {
    Queue::fake();
    $project = Project::factory()->create();
    $foreign = Idea::factory()->create();

    $result = (new DraftArticle($project))->handle(new Request(['idea_id' => $foreign->id]));

    expect((string) $result)->toContain('No open article idea');
    Queue::assertNothingPushed();
});

it('dismisses an idea for good', function () {
    $project = Project::factory()->create();
    $idea = Idea::factory()->create(['project_id' => $project->id]);

    (new DismissArticleIdea($project))->handle(new Request(['idea_id' => $idea->id]));

    expect($idea->fresh()->status)->toBe(IdeaStatus::Dismissed);
});
