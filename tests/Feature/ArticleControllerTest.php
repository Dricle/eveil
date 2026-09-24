<?php

use App\Enums\ArticleFrequency;
use App\Enums\ArticleStatus;
use App\Enums\IdeaStatus;
use App\Jobs\FetchPublishedArticle;
use App\Jobs\GenerateArticle;
use App\Models\Article;
use App\Models\Idea;
use App\Models\Organization;
use App\Models\Project;
use App\Models\User;
use Illuminate\Support\Facades\Queue;

function articleSetup(): array
{
    $organization = Organization::factory()->create();
    $user = User::factory()->create();
    $organization->users()->attach($user, ['role' => 'owner']);
    $project = Project::factory()->for($organization)->create();

    return [$user, $project];
}

it('lists this project\'s articles on the SEO page', function () {
    [$user, $project] = articleSetup();
    Article::factory()->create(['project_id' => $project->id]);
    Article::factory()->create();

    $this->actingAs($user)
        ->get(route('seo.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('seo/Index')
            ->has('articles', 1)
            ->where('frequency', 'off'));
});

it('edits a draft\'s title, meta description and body', function () {
    [$user, $project] = articleSetup();
    $article = Article::factory()->create(['project_id' => $project->id]);

    $this->actingAs($user)
        ->from(route('seo.index'))
        ->put(route('seo.articles.update', $article), [
            'title' => 'New title',
            'meta_description' => 'New meta',
            'body' => 'New body',
        ])
        ->assertRedirect(route('seo.index'));

    expect($article->fresh())
        ->title->toBe('New title')
        ->meta_description->toBe('New meta')
        ->body->toBe('New body');
});

it('never edits an article that is already live', function () {
    [$user, $project] = articleSetup();
    $article = Article::factory()->published()->create(['project_id' => $project->id]);

    $this->actingAs($user)
        ->put(route('seo.articles.update', $article), ['title' => 'New title', 'body' => 'New body'])
        ->assertNotFound();
});

it('marks an article published with its URL and reads the page', function () {
    Queue::fake();
    [$user, $project] = articleSetup();
    $article = Article::factory()->create(['project_id' => $project->id]);

    $this->actingAs($user)
        ->from(route('seo.index'))
        ->post(route('seo.articles.publish', $article), ['published_url' => 'https://acme.test/blog/widgets'])
        ->assertRedirect(route('seo.index'));

    expect($article->fresh())
        ->status->toBe(ArticleStatus::Published)
        ->published_url->toBe('https://acme.test/blog/widgets')
        ->published_at->not->toBeNull();
    Queue::assertPushed(FetchPublishedArticle::class, fn (FetchPublishedArticle $job): bool => $job->url === 'https://acme.test/blog/widgets');
});

it('refuses to mark published without a URL', function () {
    [$user, $project] = articleSetup();
    $article = Article::factory()->create(['project_id' => $project->id]);

    $this->actingAs($user)
        ->postJson(route('seo.articles.publish', $article), ['published_url' => 'not a url'])
        ->assertJsonValidationErrors('published_url');

    expect($article->fresh()->status)->toBe(ArticleStatus::Draft);
});

it('rejects an article with a reason, keeping the row', function () {
    [$user, $project] = articleSetup();
    $article = Article::factory()->create(['project_id' => $project->id]);

    $this->actingAs($user)
        ->post(route('seo.articles.reject', $article), ['reason' => 'Too generic.']);

    expect($article->fresh())
        ->status->toBe(ArticleStatus::Rejected)
        ->rejection_reason->toBe('Too generic.');
});

it('deletes an article', function () {
    [$user, $project] = articleSetup();
    $article = Article::factory()->create(['project_id' => $project->id]);

    $this->actingAs($user)->delete(route('seo.articles.destroy', $article));

    expect(Article::query()->count())->toBe(0);
});

it('cannot reach another project\'s article by id', function () {
    [$user] = articleSetup();
    $foreign = Article::factory()->create();

    $this->actingAs($user)->delete(route('seo.articles.destroy', $foreign))->assertNotFound();

    expect(Article::query()->withoutGlobalScopes()->count())->toBe(1);
});

it('saves the cadence and makes the project due right away', function () {
    [$user, $project] = articleSetup();

    $this->actingAs($user)
        ->put(route('seo.articles.cadence'), ['article_frequency' => 'weekly'])
        ->assertRedirect(route('seo.index'));

    expect($project->fresh())
        ->article_frequency->toBe(ArticleFrequency::Weekly)
        ->article_next_at->not->toBeNull();
});

it('queues an article on demand', function () {
    Queue::fake();
    [$user, $project] = articleSetup();

    $this->actingAs($user)->post(route('seo.articles.generate'))->assertRedirect(route('seo.index'));

    Queue::assertPushed(GenerateArticle::class, fn (GenerateArticle $job): bool => $job->project->is($project) && $job->brief === null);
});

it('shows only the open article ideas', function () {
    [$user, $project] = articleSetup();
    Idea::factory()->create(['project_id' => $project->id]);
    Idea::factory()->create(['project_id' => $project->id, 'status' => IdeaStatus::Dismissed]);
    Idea::factory()->create();

    $this->actingAs($user)
        ->get(route('seo.index'))
        ->assertInertia(fn ($page) => $page->has('ideas', 1));
});

it('writes an idea on demand', function () {
    Queue::fake();
    [$user, $project] = articleSetup();
    $idea = Idea::factory()->create(['project_id' => $project->id]);

    $this->actingAs($user)->post(route('seo.ideas.write', $idea))->assertRedirect();

    Queue::assertPushed(GenerateArticle::class, fn (GenerateArticle $job): bool => $job->ideaId === $idea->id);
});

it('dismisses an idea for good', function () {
    [$user, $project] = articleSetup();
    $idea = Idea::factory()->create(['project_id' => $project->id]);

    $this->actingAs($user)->post(route('seo.ideas.dismiss', $idea));

    expect($idea->fresh()->status)->toBe(IdeaStatus::Dismissed);
});

it('cannot reach another project\'s idea by id', function () {
    [$user] = articleSetup();
    $foreign = Idea::factory()->create();

    $this->actingAs($user)->post(route('seo.ideas.dismiss', $foreign))->assertNotFound();

    expect($foreign->fresh()->status)->toBe(IdeaStatus::Open);
});
