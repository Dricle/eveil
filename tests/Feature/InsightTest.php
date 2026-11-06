<?php

use App\Ai\Tools\ListInsights;
use App\Ai\Tools\UpdateInsight;
use App\Console\Commands\FindFeatureGapsCommand;
use App\Enums\InsightKind;
use App\Enums\InsightStatus;
use App\Jobs\FindFeatureGaps;
use App\Models\Insight;
use App\Models\Organization;
use App\Models\Project;
use App\Models\User;
use Illuminate\Support\Facades\Queue;
use Laravel\Ai\Tools\Request;

/**
 * @return array{0: User, 1: Project}
 */
function insightSetup(): array
{
    $organization = Organization::factory()->create();
    $user = User::factory()->create();
    $organization->users()->attach($user, ['role' => 'owner']);
    $project = Project::factory()->for($organization)->create(['knowledge_base' => ['competitors' => ['RouteCo', 'Fleetly']]]);

    return [$user, $project];
}

it('lists this project\'s open insights, newest first, with the unread count', function () {
    [$user, $project] = insightSetup();
    Insight::factory()->for($project)->recommendation('referral_program')->create();
    $mention = Insight::factory()->for($project)->competitorMention('RouteCo')->create();
    Insight::factory()->for($project)->create(['status' => InsightStatus::Dismissed]);
    Insight::factory()->for($project)->create(['read_at' => now()]);
    Insight::factory()->competitorMention()->create();

    $this->actingAs($user)
        ->get(route('insights.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('insights/Index')
            ->has('insights.data', 3)
            ->where('insights.data.0.id', Insight::query()->where('project_id', $project->id)->where('status', InsightStatus::Open)->max('id'))
            ->where('unreadCount', 2)
            ->where('competitors', ['RouteCo', 'Fleetly']));

    $this->actingAs($user)
        ->get(route('insights.index', ['kind' => 'competitor_mention', 'competitor' => 'RouteCo']))
        ->assertInertia(fn ($page) => $page->has('insights.data', 1)->where('insights.data.0.id', $mention->id));
});

it('marks one insight read and unread, and dismisses it', function () {
    [$user, $project] = insightSetup();
    $idea = Insight::factory()->for($project)->competitorMention()->create();

    $this->actingAs($user)->put(route('insights.update', $idea), ['read' => true])->assertRedirect();
    expect($idea->fresh()->read_at)->not->toBeNull();

    $this->actingAs($user)->put(route('insights.update', $idea), ['read' => false]);
    expect($idea->fresh()->read_at)->toBeNull();

    $this->actingAs($user)->put(route('insights.update', $idea), ['status' => 'dismissed']);
    expect($idea->fresh())
        ->status->toBe(InsightStatus::Dismissed)
        ->read_at->not->toBeNull();
});

it('marking a recommendation done here is what the recommendation API reads', function () {
    [$user, $project] = insightSetup();
    $idea = Insight::factory()->for($project)->recommendation('referral_program')->create();

    $this->actingAs($user)->put(route('insights.update', $idea), ['status' => 'used']);

    expect($project->recommendations()[0]['status'])->toBe('done')
        ->and($project->openRecommendations())->toBe([]);
});

it('refuses to put an insight back to open', function () {
    [$user, $project] = insightSetup();
    $idea = Insight::factory()->for($project)->create(['status' => InsightStatus::Dismissed]);

    $this->actingAs($user)->put(route('insights.update', $idea), ['status' => 'open'])->assertSessionHasErrors('status');

    expect($idea->fresh()->status)->toBe(InsightStatus::Dismissed);
});

it('marks everything of this project read at once', function () {
    [$user, $project] = insightSetup();
    $ideas = Insight::factory()->count(2)->for($project)->competitorMention()->create();
    $foreign = Insight::factory()->competitorMention()->create();

    $this->actingAs($user)->post(route('insights.read'))->assertRedirect();

    expect($ideas->map->fresh()->every(fn (Insight $idea): bool => $idea->read_at !== null))->toBeTrue()
        ->and($foreign->fresh()->read_at)->toBeNull();
});

it('cannot reach another project\'s insight by id', function () {
    [$user] = insightSetup();
    $foreign = Insight::factory()->competitorMention()->create();

    $this->actingAs($user)->put(route('insights.update', $foreign), ['status' => 'dismissed'])->assertNotFound();

    expect($foreign->fresh()->status)->toBe(InsightStatus::Open);
});

it('queues the weekly feature-gap pass only for projects that name competitors', function () {
    Queue::fake();
    [, $withCompetitors] = insightSetup();
    Project::factory()->create(['knowledge_base' => ['competitors' => []]]);
    Project::factory()->create(['knowledge_base' => null]);

    $this->artisan(FindFeatureGapsCommand::class)->assertSuccessful();

    Queue::assertPushed(FindFeatureGaps::class, 1);
    Queue::assertPushed(FindFeatureGaps::class, fn (FindFeatureGaps $job): bool => $job->project->is($withCompetitors));
});

it('moves the JSON recommendations into ideas, statuses mapped, and empties the column', function () {
    $migration = require database_path('migrations/2026_11_06_100000_rename_ideas_to_insights.php');
    $migration->down();

    $project = Project::factory()->create(['knowledge_base' => [
        'what_it_does' => 'Schedules deliveries.',
        'recommendations' => [
            ['key' => 'referral_program', 'idea' => 'Referral program', 'evidence' => 'No referral flow.', 'impact' => 'high', 'effort' => 'medium'],
            ['key' => 'feature_csv_import', 'idea' => 'CSV import', 'evidence' => 'RouteCo has it.', 'impact' => 'low', 'effort' => 'low', 'status' => 'archived', 'kind' => 'feature'],
            ['key' => 'case_studies', 'idea' => 'Case studies', 'evidence' => 'None.', 'impact' => 'low', 'effort' => 'low', 'status' => 'done'],
        ],
    ]]);

    $migration->up();

    $project->refresh();
    $recommendations = collect($project->recommendations())->keyBy('key');

    expect($project->knowledge_base)->toBe(['what_it_does' => 'Schedules deliveries.'])
        ->and($recommendations['referral_program'])->toMatchArray(['status' => 'proposed', 'kind' => 'acquisition', 'impact' => 'high', 'evidence' => 'No referral flow.'])
        ->and($recommendations['feature_csv_import'])->toMatchArray(['status' => 'archived', 'kind' => 'feature'])
        ->and($recommendations['case_studies']['status'])->toBe('done')
        ->and(Insight::query()->where('source_ref', 'referral_program')->sole()->read_at)->toBeNull()
        ->and(Insight::query()->where('kind', InsightKind::Feature)->sole()->read_at)->not->toBeNull();
});

it('lets Evie read the feed by competitor and dismiss an item', function () {
    [, $project] = insightSetup();
    $mention = Insight::factory()->for($project)->competitorMention('RouteCo')->create(['angle' => 'Too expensive past ten drivers.']);
    Insight::factory()->for($project)->competitorMention('Fleetly')->create();

    $listed = json_decode((string) (new ListInsights($project))->handle(new Request(['competitor' => 'routeco'])), true);

    expect($listed)->toHaveCount(1)
        ->and($listed[0]['summary'])->toBe('Too expensive past ten drivers.');

    (new UpdateInsight($project))->handle(new Request(['insight_id' => $mention->id, 'status' => 'dismissed']));

    expect($mention->fresh()->status)->toBe(InsightStatus::Dismissed);
});
