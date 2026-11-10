<?php

use App\Actions\NoteArticleIdeas;
use App\Ai\Agents\ArticleTopicMatcher;
use App\Ai\OutOfCredit;
use App\Enums\InsightKind;
use App\Enums\InsightStatus;
use App\Models\AgentRun;
use App\Models\Article;
use App\Models\Insight;
use App\Models\Project;
use App\Support\CurrentProject;

/**
 * @param  list<string>  $angles  one thread per angle, numbered in order
 */
function noteIdeas(Project $project, array $angles): void
{
    $threads = collect($angles)->map(fn (string $angle, int $i): array => [
        'angle' => $angle,
        'title' => "Thread {$i}",
        'url' => "https://www.reddit.com/r/SaaS/comments/t{$i}/",
        'subreddit' => 'SaaS',
    ]);

    app(CurrentProject::class)->run($project, fn () => app(NoteArticleIdeas::class)->handle($project, $threads));
}

/**
 * @param  list<array{0: string, 1: int}>  $verdicts  [verdict, ref] per angle, in order
 */
function fakeTopicVerdicts(array $verdicts): void
{
    ArticleTopicMatcher::fake([[
        'items' => collect($verdicts)->map(fn (array $verdict, int $i): array => ['index' => $i, 'verdict' => $verdict[0], 'ref' => $verdict[1]])->all(),
    ]]);
}

it('opens a topic for a lone first angle without asking the model', function () {
    $project = Project::factory()->create();

    noteIdeas($project, ['How to find first customers']);

    expect(Insight::sole())
        ->kind->toBe(InsightKind::Article)
        ->angle->toBe('How to find first customers')
        ->mentions->toBe(1)
        ->details->toEqual(['threads' => [['title' => 'Thread 0', 'url' => 'https://www.reddit.com/r/SaaS/comments/t0/', 'subreddit' => 'SaaS']]])
        ->and(AgentRun::count())->toBe(0);
});

it('adds a thread asking the same question to the open topic and counts it', function () {
    $project = Project::factory()->create();
    $topic = Insight::factory()->for($project)->create(['angle' => 'How to find first customers', 'details' => ['threads' => [['title' => 'Old', 'url' => 'https://old.test/']]]]);
    fakeTopicVerdicts([['topic', $topic->id]]);

    noteIdeas($project, ['Landing your first five customers']);

    expect(Insight::sole()->id)->toBe($topic->id)
        ->and($topic->fresh()->mentions)->toBe(2)
        ->and(collect($topic->fresh()->details['threads'])->pluck('url')->all())->toBe(['https://old.test/', 'https://www.reddit.com/r/SaaS/comments/t0/'])
        ->and(AgentRun::sole()->input['prompt'])->toContain("[{$topic->id}] How to find first customers");
});

it('groups angles of the same scan that ask the same question', function () {
    $project = Project::factory()->create();
    fakeTopicVerdicts([['new', 0], ['new', 0], ['same_as', 0]]);

    noteIdeas($project, ['First customers', 'Mailbox warm-up', 'First clients without a list']);

    expect(Insight::query()->orderBy('id')->get(['angle', 'mentions'])->toArray())->toBe([
        ['angle' => 'First customers', 'mentions' => 2],
        ['angle' => 'Mailbox warm-up', 'mentions' => 1],
    ]);
});

it('drops an angle an article already answers, and one merged into it', function () {
    $project = Project::factory()->create();
    $article = Article::factory()->published()->create(['project_id' => $project->id, 'title' => 'How to get your first 5 customers']);
    fakeTopicVerdicts([['article', $article->id], ['same_as', 0]]);

    noteIdeas($project, ['First customers', 'First clients']);

    expect(Insight::count())->toBe(0)
        ->and(AgentRun::sole()->input['prompt'])->toContain("[{$article->id}] How to get your first 5 customers");
});

it('never brings back a thread the user dismissed, nor shows the model a dismissed topic', function () {
    $project = Project::factory()->create();
    Insight::factory()->for($project)->create(['source_ref' => 'https://www.reddit.com/r/SaaS/comments/t0/', 'status' => InsightStatus::Dismissed]);
    Insight::factory()->for($project)->create(['angle' => 'A dismissed question', 'status' => InsightStatus::Dismissed]);
    Article::factory()->published()->create(['project_id' => $project->id]);
    fakeTopicVerdicts([['new', 0]]);

    noteIdeas($project, ['The dismissed thread again', 'A new thread']);

    expect(Insight::query()->where('status', InsightStatus::Open)->pluck('angle')->all())->toBe(['A new thread'])
        ->and(AgentRun::sole()->input['prompt'])->not->toContain('A dismissed question');
});

it('keeps an angle as a new topic when the model points at an id that does not exist', function () {
    $project = Project::factory()->create();
    Article::factory()->published()->create(['project_id' => $project->id]);
    fakeTopicVerdicts([['topic', 999999]]);

    noteIdeas($project, ['A good idea']);

    expect(Insight::sole()->angle)->toBe('A good idea');
});

it('never counts the same thread twice', function () {
    $project = Project::factory()->create();

    noteIdeas($project, ['How to find first customers']);
    noteIdeas($project, ['How to find first customers']);

    expect(Insight::sole()->mentions)->toBe(1)
        ->and(AgentRun::count())->toBe(0);
});

it('forgets a topic nobody asked about again for a month, never a popular one', function () {
    $project = Project::factory()->create();
    $lone = Insight::factory()->for($project)->create(['created_at' => now()->subDays(Insight::LONE_TOPIC_DAYS + 1)]);
    $popular = Insight::factory()->for($project)->create(['mentions' => 2, 'created_at' => now()->subDays(Insight::LONE_TOPIC_DAYS + 1)]);
    $recent = Insight::factory()->for($project)->create();

    noteIdeas($project, []);

    expect(Insight::query()->pluck('id')->sort()->values()->all())->toBe([$popular->id, $recent->id])
        ->and(Insight::find($lone->id))->toBeNull();
});

it('regroups the open ideas already noted, keeping every thread', function () {
    $project = Project::factory()->create();
    $first = Insight::factory()->for($project)->create(['angle' => 'First customers']);
    $second = Insight::factory()->for($project)->create(['angle' => 'First clients']);
    fakeTopicVerdicts([['new', 0], ['same_as', 0]]);

    $this->artisan('eveil:group-article-ideas')->assertSuccessful();

    expect(Insight::sole())
        ->angle->toBe('First customers')
        ->mentions->toBe(2)
        ->and(collect(Insight::sole()->details['threads'])->pluck('url')->all())->toBe([$first->source_ref, $second->source_ref]);
});

it('leaves a failing project\'s ideas as they were and still regroups the others', function () {
    $broke = Project::factory()->create();
    $brokeIdeas = Insight::factory()->count(2)->for($broke)->create();
    $fine = Project::factory()->create();
    Insight::factory()->for($fine)->create(['angle' => 'First customers']);
    Insight::factory()->for($fine)->create(['angle' => 'First clients']);
    ArticleTopicMatcher::fake(function (string $prompt): array {
        if (str_contains($prompt, 'First customers')) {
            return ['items' => [['index' => 0, 'verdict' => 'new', 'ref' => 0], ['index' => 1, 'verdict' => 'same_as', 'ref' => 0]]];
        }

        throw new OutOfCredit('This project has no credits left.');
    });

    $this->artisan('eveil:group-article-ideas')->assertSuccessful();

    expect(Insight::query()->where('project_id', $broke->id)->pluck('id')->sort()->values()->all())->toBe($brokeIdeas->modelKeys())
        ->and(Insight::query()->where('project_id', $fine->id)->sole()->mentions)->toBe(2);
});
