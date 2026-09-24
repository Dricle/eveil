<?php

use App\Models\Article;
use App\Models\Project;
use App\Support\Settings;

function bootCloudBlog(Project $project): void
{
    config()->set('eveil.edition', 'cloud');
    app(Settings::class)->set('blog.project_id', $project->id);
}

afterEach(fn () => config()->set('eveil.edition', 'self'));

it('has no blog on a self-hosted instance', function () {
    $this->get('/blog')->assertNotFound();
});

it('lists only the blog project\'s published articles', function () {
    $project = Project::factory()->create();
    bootCloudBlog($project);
    Article::factory()->published()->create(['project_id' => $project->id, 'title' => 'Live post']);
    Article::factory()->create(['project_id' => $project->id, 'title' => 'Still a draft']);
    Article::factory()->published()->create(['title' => 'Someone else\'s post']);

    $this->get('/blog')
        ->assertOk()
        ->assertSee('Live post')
        ->assertDontSee('Still a draft')
        ->assertDontSee('Someone else');
});

it('renders an article from its Markdown, with raw HTML stripped', function () {
    $project = Project::factory()->create();
    bootCloudBlog($project);
    $article = Article::factory()->published()->create([
        'project_id' => $project->id,
        'body' => "## Why widgets\n\nThey **work**.\n\n<script>alert(1)</script>",
    ]);

    $this->get("/blog/{$article->id}/whatever-the-title-was")
        ->assertOk()
        ->assertSee('<h2>Why widgets</h2>', escape: false)
        ->assertSee('<strong>work</strong>', escape: false)
        ->assertDontSee('<script>alert(1)</script>', escape: false);
});

it('never serves a draft or another project\'s article', function () {
    $project = Project::factory()->create();
    bootCloudBlog($project);
    $draft = Article::factory()->create(['project_id' => $project->id]);
    $foreign = Article::factory()->published()->create();

    $this->get("/blog/{$draft->id}")->assertNotFound();
    $this->get("/blog/{$foreign->id}")->assertNotFound();
});
