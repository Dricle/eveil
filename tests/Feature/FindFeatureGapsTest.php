<?php

use App\Actions\AnalyzeWebsite;
use App\Actions\FindFeatureGaps;
use App\Ai\Agents\CompetitorAnalyst;
use App\Enums\AgentRunStatus;
use App\Jobs\AnalyzeProject;
use App\Jobs\FindFeatureGaps as FindFeatureGapsJob;
use App\Models\AgentRun;
use App\Models\Organization;
use App\Models\Project;
use App\Models\ProjectAnalysis;
use App\Services\Discovery\SiteCrawler;
use App\Services\Discovery\WebsiteFinder;
use App\Support\ParsedPage;
use Illuminate\Support\Facades\Queue;
use Laravel\Ai\Responses\Data\Meta;
use Laravel\Ai\Responses\Data\TextUsage;
use Laravel\Ai\Responses\StructuredTextResponse;

/**
 * @return array{0: Project, 1: AgentRun}
 */
function projectWithCompetitors(): array
{
    $project = Project::factory()->for(Organization::factory())->create([
        'knowledge_base' => [
            'what_it_does' => 'Schedules deliveries for regional wholesalers.',
            'product_category' => 'delivery scheduling software',
            'competitors' => ['RouteCo'],
            'recommendations' => [
                ['key' => 'referral_program', 'idea' => 'Referral program', 'evidence' => 'No referral flow.', 'impact' => 'high', 'effort' => 'medium'],
                ['key' => 'feature_csv_import', 'idea' => 'CSV import', 'evidence' => 'RouteCo has it.', 'impact' => 'low', 'effort' => 'low', 'status' => 'archived', 'kind' => 'feature'],
                ['key' => 'feature_old_gap', 'idea' => 'Old gap', 'evidence' => 'RouteCo had it.', 'impact' => 'low', 'effort' => 'low', 'kind' => 'feature'],
            ],
        ],
    ]);

    $run = AgentRun::create(['project_id' => $project->id, 'agent' => CompetitorAnalyst::slug(), 'status' => AgentRunStatus::Pending]);

    return [$project, $run];
}

it('writes feature gaps without touching open acquisition ideas or decided gaps', function () {
    [$project, $run] = projectWithCompetitors();

    $this->mock(WebsiteFinder::class, fn ($mock) => $mock->shouldReceive('find')
        ->once()->with('RouteCo', 'delivery scheduling software', Mockery::any())->andReturn('https://routeco.test'));
    $this->mock(SiteCrawler::class, fn ($mock) => $mock->shouldReceive('crawl')
        ->once()->andReturn(collect([new ParsedPage('https://routeco.test', 'Features', 'en', 'Driver app, CSV import.')])));

    CompetitorAnalyst::fake([
        new StructuredTextResponse(
            ['recommendations' => [
                ['key' => 'feature_driver_app', 'idea' => 'Driver mobile app', 'evidence' => 'RouteCo sells one.', 'impact' => 'high', 'effort' => 'high'],
                ['key' => 'feature_csv_import', 'idea' => 'CSV import again', 'evidence' => 'RouteCo has it.', 'impact' => 'high', 'effort' => 'low'],
            ]],
            '{}',
            new TextUsage(inputTokens: 10, outputTokens: 5),
            new Meta('anthropic', 'claude-opus-5'),
        ),
    ]);

    app(FindFeatureGaps::class)->handle($project, $run);

    $recommendations = collect($project->fresh()->recommendations())->keyBy('key');

    expect($recommendations->keys()->sort()->values()->all())->toBe(['feature_csv_import', 'feature_driver_app', 'referral_program'])
        ->and($recommendations['feature_driver_app'])->toMatchArray(['status' => 'proposed', 'kind' => 'feature'])
        // Archived never comes back (ADR-032).
        ->and($recommendations['feature_csv_import'])->toMatchArray(['status' => 'archived', 'idea' => 'CSV import'])
        ->and($recommendations['referral_program'])->toMatchArray(['status' => 'proposed', 'kind' => 'acquisition'])
        ->and($run->fresh()->status)->toBe(AgentRunStatus::Succeeded);
});

it('fails the run and writes nothing when no competitor site is found', function () {
    [$project, $run] = projectWithCompetitors();

    $this->mock(WebsiteFinder::class, fn ($mock) => $mock->shouldReceive('find')->once()->andReturn(null));
    CompetitorAnalyst::fake()->preventStrayPrompts();

    app(FindFeatureGaps::class)->handle($project, $run);

    expect($run->fresh())
        ->status->toBe(AgentRunStatus::Failed)
        ->error->not->toBeNull()
        ->and($project->fresh()->recommendations())->toHaveCount(3);
});

it('queues the feature-gap pass once an analysis has named competitors, and only then', function (array $competitors, bool $queued) {
    Queue::fake();

    $project = Project::factory()->for(Organization::factory())->create(['knowledge_base' => ['competitors' => $competitors]]);

    $this->mock(AnalyzeWebsite::class, fn ($mock) => $mock->shouldReceive('handle')->once()->andReturn(new ProjectAnalysis));

    app()->call([new AnalyzeProject($project), 'handle']);

    $queued
        ? Queue::assertPushed(FindFeatureGapsJob::class, fn (FindFeatureGapsJob $job): bool => $job->project->is($project))
        : Queue::assertNotPushed(FindFeatureGapsJob::class);
})->with([
    'competitors named' => [['RouteCo'], true],
    'none named' => [[], false],
]);
