<?php

use App\Models\Project;
use App\Models\RedditReply;
use App\Services\Reddit\SeoThreadFinder;
use App\Support\CurrentProject;
use Illuminate\Support\Facades\Http;

it('derives queries from the product category and each named competitor', function () {
    $project = Project::factory()->create([
        'knowledge_base' => [
            'product_category' => 'invoicing software',
            'competitors' => ['Acme', 'Beta Corp'],
        ],
    ]);

    expect(app(SeoThreadFinder::class)->queries($project)->all())->toBe([
        'best invoicing software',
        'Acme alternative',
        'Beta Corp alternative',
    ]);
});

it('finds nothing when the knowledge base has neither a category nor competitors', function () {
    $project = Project::factory()->create(['knowledge_base' => []]);
    Http::fake();

    expect(app(SeoThreadFinder::class)->find($project))->toBeEmpty();
    Http::assertNothingSent();
});

it('reads a matching search result into a candidate, with its top comments', function () {
    $project = Project::factory()->create(['knowledge_base' => ['product_category' => 'invoicing software']]);

    Http::fake([
        'searxng*' => Http::response(['results' => [
            ['title' => 'Best invoicing tools 2026', 'content' => '...', 'url' => 'https://www.reddit.com/r/smallbusiness/comments/abc123/best_invoicing_tools/?utm_source=x'],
        ]]),
        'arctic-shift.photon-reddit.com/api/posts/search*' => Http::response(['data' => [[
            'id' => 'abc123',
            'title' => 'Best invoicing tools 2026',
            'selftext' => 'What do you all use?',
            'subreddit' => 'smallbusiness',
            'author' => 'asker1',
        ]]]),
        'arctic-shift.photon-reddit.com/api/comments/search*' => Http::response(['data' => [[
            'body' => 'I use an old spreadsheet, honestly.',
            'score' => 12,
            'author' => 'replier1',
        ]]]),
    ]);

    $candidates = app(SeoThreadFinder::class)->find($project);

    expect($candidates)->toHaveCount(1);

    $candidate = $candidates->first();

    expect($candidate->permalink)->toBe('https://www.reddit.com/r/smallbusiness/comments/abc123/best_invoicing_tools/')
        ->and($candidate->subreddit)->toBe('smallbusiness')
        ->and($candidate->source->value)->toBe('seo_thread')
        ->and($candidate->searchQuery)->toBe('best invoicing software')
        ->and($candidate->threadTitle)->toBe('Best invoicing tools 2026')
        ->and($candidate->text)->toContain('What do you all use?')
        ->and($candidate->text)->toContain('I use an old spreadsheet, honestly.')
        ->and($candidate->topComments)->toBe(['I use an old spreadsheet, honestly.']);
});

it('never revisits a thread that already has a reply row in this project', function () {
    $project = Project::factory()->create(['knowledge_base' => ['product_category' => 'invoicing software']]);
    app(CurrentProject::class)->set($project);
    RedditReply::factory()->create([
        'project_id' => $project->id,
        'thread_permalink' => 'https://www.reddit.com/r/smallbusiness/comments/abc123/best_invoicing_tools/',
    ]);

    Http::fake([
        'searxng*' => Http::response(['results' => [
            ['title' => 'Best invoicing tools 2026', 'content' => '...', 'url' => 'https://www.reddit.com/r/smallbusiness/comments/abc123/best_invoicing_tools/'],
        ]]),
    ]);

    expect(app(SeoThreadFinder::class)->find($project))->toBeEmpty();
    Http::assertNotSent(fn ($request) => str_contains($request->url(), 'arctic-shift'));
});

it('drops a search result whose host is not reddit.com', function () {
    $project = Project::factory()->create(['knowledge_base' => ['product_category' => 'invoicing software']]);

    Http::fake([
        'searxng*' => Http::response(['results' => [
            ['title' => 'A blog post', 'content' => '...', 'url' => 'https://example.com/best-invoicing-tools'],
        ]]),
    ]);

    expect(app(SeoThreadFinder::class)->find($project))->toBeEmpty();
});
