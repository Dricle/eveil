<?php

use App\Actions\NoteFeatureIdeas;
use App\Ai\Agents\FeatureTopicMatcher;
use App\Ai\OutOfCredit;
use App\Enums\InsightKind;
use App\Enums\InsightStatus;
use App\Enums\RecommendationKind;
use App\Models\AgentRun;
use App\Models\Insight;
use App\Models\Project;
use App\Support\CurrentProject;

/**
 * @param  list<string>  $ideas  one thread per idea, numbered in order
 */
function noteFeatures(Project $project, array $ideas): void
{
    $threads = collect($ideas)->map(fn (string $idea, int $i): array => [
        'idea' => $idea,
        'evidence' => "People in thread {$i} want it.",
        'title' => "Thread {$i}",
        'url' => "https://www.reddit.com/r/SaaS/comments/f{$i}/",
        'subreddit' => 'SaaS',
    ]);

    app(CurrentProject::class)->run($project, fn () => app(NoteFeatureIdeas::class)->handle($project, $threads));
}

/**
 * @param  list<array{0: string, 1: int}>  $verdicts  [verdict, ref] per idea, in order
 */
function fakeFeatureVerdicts(array $verdicts): void
{
    FeatureTopicMatcher::fake([[
        'items' => collect($verdicts)->map(fn (array $verdict, int $i): array => ['index' => $i, 'verdict' => $verdict[0], 'ref' => $verdict[1]])->all(),
    ]]);
}

it('opens a feature for a lone first request without asking the model', function () {
    $project = Project::factory()->create();

    noteFeatures($project, ['Export invoices to accounting software']);

    expect(Insight::sole())
        ->kind->toBe(InsightKind::Feature)
        ->title->toBe('Export invoices to accounting software')
        ->angle->toBe('People in thread 0 want it.')
        ->mentions->toBe(1)
        ->details->toEqual(['threads' => [['title' => 'Thread 0', 'url' => 'https://www.reddit.com/r/SaaS/comments/f0/', 'subreddit' => 'SaaS', 'evidence' => 'People in thread 0 want it.']]])
        ->and(AgentRun::count())->toBe(0);
});

it('adds a thread asking for the same capability to the open feature and counts it', function () {
    $project = Project::factory()->create();
    $feature = Insight::factory()->for($project)->featureRequest()->create(['title' => 'Track brand mentions in AI search']);
    fakeFeatureVerdicts([['feature', $feature->id]]);

    noteFeatures($project, ['Check whether ChatGPT cites my brand']);

    expect(Insight::sole()->id)->toBe($feature->id)
        ->and($feature->fresh()->mentions)->toBe(2)
        ->and(collect($feature->fresh()->details['threads'])->pluck('evidence')->last())->toBe('People in thread 0 want it.')
        ->and(AgentRun::sole()->input['prompt'])->toContain("[{$feature->id}] Track brand mentions in AI search");
});

it('groups requests of the same scan that ask for the same capability', function () {
    $project = Project::factory()->create();
    fakeFeatureVerdicts([['new', 0], ['new', 0], ['same_as', 0]]);

    noteFeatures($project, ['CRM sync', 'LinkedIn DMs', 'Push contacts to HubSpot']);

    expect(Insight::query()->orderBy('id')->get(['title', 'mentions'])->toArray())->toBe([
        ['title' => 'CRM sync', 'mentions' => 2],
        ['title' => 'LinkedIn DMs', 'mentions' => 1],
    ]);
});

it('never brings back a feature already built or dismissed, nor one merged into it', function () {
    $project = Project::factory()->create();
    $built = Insight::factory()->for($project)->featureRequest()->create(['status' => InsightStatus::Used]);
    fakeFeatureVerdicts([['feature', $built->id], ['same_as', 0], ['new', 0]]);

    noteFeatures($project, ['The built one again', 'Same again', 'Something new']);

    expect(Insight::query()->where('status', InsightStatus::Open)->pluck('title')->all())->toBe(['Something new'])
        ->and($built->fresh()->mentions)->toBe(1);
});

it('keeps a request as a new feature when the model points at an id that does not exist', function () {
    $project = Project::factory()->create();
    Insight::factory()->for($project)->featureRequest()->create();
    fakeFeatureVerdicts([['feature', 999999]]);

    noteFeatures($project, ['A real need']);

    expect(Insight::query()->where('title', 'A real need')->exists())->toBeTrue();
});

it('never counts the same thread twice', function () {
    $project = Project::factory()->create();

    noteFeatures($project, ['Export invoices']);
    noteFeatures($project, ['Export invoices']);

    expect(Insight::sole()->mentions)->toBe(1)
        ->and(AgentRun::count())->toBe(0);
});

it('lets a competitor re-reading update a feature Reddit joined without losing or dropping its threads', function () {
    $project = Project::factory()->create();
    Insight::factory()->for($project)->recommendation('crm_sync', 'CRM sync', kind: InsightKind::Feature)
        ->create(['details' => ['impact' => 'high', 'effort' => 'medium', 'threads' => [['title' => 'T', 'url' => 'https://reddit.test/1']]]]);
    Insight::factory()->for($project)->recommendation('linkedin_dms', 'LinkedIn DMs', kind: InsightKind::Feature)
        ->create(['details' => ['impact' => 'high', 'effort' => 'high', 'threads' => [['title' => 'T', 'url' => 'https://reddit.test/2']]]]);

    $project->replaceOpenRecommendations([['key' => 'crm_sync', 'idea' => 'Two-way CRM sync', 'evidence' => 'x', 'impact' => 'medium', 'effort' => 'low']], RecommendationKind::Feature);

    $crm = Insight::query()->where('source_ref', 'crm_sync')->sole();

    expect($crm->title)->toBe('Two-way CRM sync')
        ->and($crm->details['threads'])->toHaveCount(1)
        ->and(Insight::query()->where('source_ref', 'linkedin_dms')->exists())->toBeTrue();
});

it('regroups the open features from Reddit, leaving the competitor ones and keeping every thread', function () {
    $project = Project::factory()->create();
    $competitor = Insight::factory()->for($project)->recommendation('ai_visibility', 'AI search visibility', kind: InsightKind::Feature)->create();
    // Noted before grouping: its one thread lives in its own columns.
    $legacy = Insight::factory()->for($project)->create([
        'kind' => InsightKind::Feature,
        'title' => 'Track brand mentions in ChatGPT',
        'url' => 'https://www.reddit.com/r/SaaS/comments/old/',
        'source_ref' => 'https://www.reddit.com/r/SaaS/comments/old/',
        'details' => ['subreddit' => 'SaaS', 'thread_title' => 'How do you track AI mentions?'],
    ]);
    Insight::factory()->for($project)->featureRequest()->create(['title' => 'Weekly AI search mention report']);
    fakeFeatureVerdicts([['feature', $competitor->id], ['same_as', 0]]);

    $this->artisan('eveil:group-feature-ideas')->assertSuccessful();

    expect(Insight::sole()->id)->toBe($competitor->id)
        ->and($competitor->fresh()->mentions)->toBe(2)
        ->and(collect($competitor->fresh()->details['threads'])->pluck('title')->first())->toBe('How do you track AI mentions?')
        ->and(collect($competitor->fresh()->details['threads'])->pluck('url')->first())->toBe($legacy->url);
});

it('leaves a project\'s features as they were when it is out of credit', function () {
    $project = Project::factory()->create();
    $features = Insight::factory()->count(2)->for($project)->featureRequest()->create();
    FeatureTopicMatcher::fake(fn () => throw new OutOfCredit('This project has no credits left.'));

    $this->artisan('eveil:group-feature-ideas')->assertSuccessful();

    expect(Insight::query()->pluck('id')->sort()->values()->all())->toBe($features->modelKeys());
});
