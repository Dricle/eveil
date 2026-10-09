<?php

use App\Models\Article;
use App\Models\Project;
use App\Support\Settings;

function bootCloudSite(Project $project): void
{
    config()->set('eveil.edition', 'cloud');
    app(Settings::class)->set('blog.project_id', $project->id);
}

afterEach(fn () => config()->set('eveil.edition', 'self'));

it('has no sitemap on a self-hosted instance', function () {
    $this->get('/sitemap.xml')->assertNotFound();
});

it('does not point a self-hosted crawler at a sitemap it has not got', function () {
    $this->get('/robots.txt')
        ->assertOk()
        ->assertSee('User-agent: *')
        ->assertDontSee('Sitemap:');
});

it('tells a crawler where the sitemap is', function () {
    bootCloudSite(Project::factory()->create());

    $this->get('/robots.txt')
        ->assertOk()
        ->assertSee('Sitemap: '.route('sitemap'));
});

it('lists the marketing pages and every published article', function () {
    $project = Project::factory()->create();
    bootCloudSite($project);
    $article = Article::factory()->published()->create([
        'project_id' => $project->id,
        'title' => 'Live post',
    ]);
    Article::factory()->create(['project_id' => $project->id, 'title' => 'Still a draft']);
    $foreign = Article::factory()->published()->create(['title' => 'Someone else\'s post']);

    $response = $this->get('/sitemap.xml')->assertOk();

    expect($response->headers->get('Content-Type'))->toContain('xml');

    foreach (['home', 'blog.index', 'contact', 'privacy', 'terms', 'data-retention'] as $name) {
        $response->assertSee('<loc>'.route($name).'</loc>', escape: false);
    }

    $response->assertSee(route('blog.show', [$article->id, 'live-post']), escape: false);
    $response->assertDontSee('still-a-draft');
    $response->assertDontSee((string) $foreign->id.'/someone');
});

it('is well-formed XML', function () {
    $project = Project::factory()->create();
    bootCloudSite($project);
    Article::factory()->published()->create(['project_id' => $project->id, 'title' => 'Ampersands & angle brackets']);

    $body = $this->get('/sitemap.xml')->assertOk()->getContent();

    expect($body)->toStartWith('<?xml version="1.0" encoding="UTF-8"?>');
    expect(simplexml_load_string($body))->not->toBeFalse();
});
