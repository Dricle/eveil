<?php

use App\Actions\RefreshAcquisitionIdeas;
use App\Ai\Agents\WebsiteAnalyst;
use App\Enums\AgentRunStatus;
use App\Models\AgentRun;
use App\Models\Organization;
use App\Models\Project;
use App\Services\Discovery\SiteCrawler;
use App\Support\ParsedPage;
use Illuminate\Support\Collection;
use Laravel\Ai\Responses\Data\Meta;
use Laravel\Ai\Responses\Data\Usage;
use Laravel\Ai\Responses\StructuredTextResponse;

/**
 * @return array{0: Project, 1: AgentRun}
 */
function projectPendingRefresh(): array
{
    $project = Project::factory()->for(Organization::factory())->create([
        'knowledge_base' => [
            'what_it_does' => 'Schedules deliveries for regional wholesalers.',
            'key_features' => ['Route planning'],
            'recommendations' => [
                ['key' => 'referral_program', 'idea' => 'Referral program', 'evidence' => 'No referral flow.', 'impact' => 'high', 'effort' => 'medium', 'status' => 'done'],
            ],
        ],
        // Set even though nothing here should read it: proves the merge
        // isn't gated on it the way a full `AnalyzeWebsite` re-analysis is.
        'knowledge_base_edited_by_user' => true,
    ]);

    $run = AgentRun::create(['project_id' => $project->id, 'agent' => WebsiteAnalyst::slug(), 'status' => AgentRunStatus::Pending]);

    return [$project, $run];
}

function onePageCrawl(): Collection
{
    return collect([new ParsedPage('https://example.test', 'Home', 'en', 'Some page text.')]);
}

it('writes only the recommendations, leaving the rest of the knowledge base untouched', function () {
    [$project, $run] = projectPendingRefresh();

    $this->mock(SiteCrawler::class, fn ($mock) => $mock->shouldReceive('crawl')->once()->andReturn(onePageCrawl()));

    WebsiteAnalyst::fake([
        new StructuredTextResponse(
            [
                'what_it_does' => 'Something completely different a hallucinated run might write.',
                'recommendations' => [
                    ['key' => 'sector_case_studies', 'idea' => 'Sector case studies', 'evidence' => 'No case studies published.', 'impact' => 'medium', 'effort' => 'low'],
                ],
            ],
            '{}',
            new Usage(promptTokens: 10, completionTokens: 5),
            new Meta('anthropic', 'claude-opus-5'),
        ),
    ]);

    app(RefreshAcquisitionIdeas::class)->handle($project, $run);

    $fresh = $project->fresh();
    $recommendations = collect($fresh->recommendations())->keyBy('key');

    expect($fresh->knowledge_base['what_it_does'])->toBe('Schedules deliveries for regional wholesalers.')
        ->and($fresh->knowledge_base_edited_by_user)->toBeTrue()
        // The decided one survives untouched (ADR-032), the new one is added.
        ->and($recommendations['referral_program']['status'])->toBe('done')
        ->and($recommendations['sector_case_studies']['status'])->toBe('proposed')
        ->and($run->fresh()->status)->toBe(AgentRunStatus::Succeeded);
});

it('marks the run failed and leaves recommendations alone on an empty crawl', function () {
    [$project, $run] = projectPendingRefresh();

    $this->mock(SiteCrawler::class, fn ($mock) => $mock->shouldReceive('crawl')->once()->andReturn(collect()));

    app(RefreshAcquisitionIdeas::class)->handle($project, $run);

    expect($run->fresh())
        ->status->toBe(AgentRunStatus::Failed)
        ->error->not->toBeNull()
        ->and($project->fresh()->recommendations())->toHaveCount(1);
});
