<?php

use App\Enums\DiscoveryRunStatus;
use App\Enums\DiscoveryTaskKind;
use App\Enums\DiscoveryTaskStatus;
use App\Jobs\Discovery\HarvestListing;
use App\Jobs\Discovery\PlanDiscovery;
use App\Models\AgentRun;
use App\Models\DiscoveryRun;
use App\Models\DiscoveryTask;
use App\Models\Organization;
use App\Models\Project;
use App\Models\TargetProfile;
use App\Models\User;
use Illuminate\Support\Facades\Queue;

function searcher(): User
{
    $user = User::factory()->create();
    Organization::factory()->create()->users()->attach($user, ['role' => 'owner']);

    return $user;
}

/**
 * @return array{0: User, 1: Project}
 */
function searcherWithProject(): array
{
    $user = searcher();

    return [$user, Project::factory()->for($user->organizations()->sole())->create()];
}

it('lists the searches one profile has been put through', function () {
    [$user, $project] = searcherWithProject();

    $profile = TargetProfile::factory()->create(['project_id' => $project->id, 'name' => 'Friteries wallonnes']);

    DiscoveryRun::factory()->create([
        'project_id' => $project->id,
        'target_profile_id' => $profile->id,
        'status' => DiscoveryRunStatus::Running,
    ]);

    // Another profile's run: a search means nothing without the criteria it was
    // given, so it belongs to that profile's page and nowhere else.
    DiscoveryRun::factory()->create([
        'project_id' => $project->id,
        'target_profile_id' => TargetProfile::factory()->create(['project_id' => $project->id])->id,
    ]);

    $this->actingAs($user)->get(route('targets.searches', $profile))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('targets/Searches')
            ->where('profile.name', 'Friteries wallonnes')
            ->count('runs', 1)
            ->where('runs.0.running', true));
});

it('draws the graph of one run, with what each node cost', function () {
    [$user, $project] = searcherWithProject();

    $run = DiscoveryRun::factory()->create([
        'project_id' => $project->id,
        'status' => DiscoveryRunStatus::Running,
        'budget' => ['max_companies' => 40, 'max_qualified' => 20, 'max_pages' => 60, 'max_queries' => 8],
        'stats' => ['plan' => 'Enumerate friteries in Charleroi on the map.'],
    ]);

    $agentRun = AgentRun::factory()->create(['project_id' => $project->id, 'tokens_in' => 900, 'tokens_out' => 300]);

    DiscoveryTask::factory()->create([
        'project_id' => $project->id,
        'discovery_run_id' => $run->id,
        'kind' => DiscoveryTaskKind::Qualify,
        'status' => DiscoveryTaskStatus::Succeeded,
        'payload' => ['domain' => 'friterie-centre.be', 'name' => 'Friterie du Centre'],
        'result' => ['prospect' => true],
        'agent_run_id' => $agentRun->id,
    ]);

    $this->actingAs($user)->get(route('discovery-runs.show', $run))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('discovery/Run')
            ->where('run.plan', 'Enumerate friteries in Charleroi on the map.')
            ->where('run.tasks.0.subject', 'friterie-centre.be')
            ->where('run.tasks.0.tokens', 1200));
});

it('never shows a run belonging to another project', function () {
    [$user] = searcherWithProject();

    $other = DiscoveryRun::factory()->create();

    $this->actingAs($user)->get(route('discovery-runs.show', $other))->assertNotFound();
});

it('starts a search for a profile', function () {
    Queue::fake();

    [$user, $project] = searcherWithProject();
    $profile = TargetProfile::factory()->create(['project_id' => $project->id]);

    $this->actingAs($user)
        ->post(route('discovery-runs.store'), ['target_profile' => $profile->id])
        ->assertRedirect(route('discovery-runs.show', DiscoveryRun::query()->sole()));

    expect(DiscoveryTask::query()->sole()->kind)->toBe(DiscoveryTaskKind::Plan);

    Queue::assertPushed(PlanDiscovery::class);
});

it('refuses to start a search for another project\'s profile', function () {
    Queue::fake();

    [$user] = searcherWithProject();
    $other = TargetProfile::factory()->create();

    $this->actingAs($user)
        ->post(route('discovery-runs.store'), ['target_profile' => $other->id])
        ->assertNotFound();

    Queue::assertNothingPushed();
});

it('stops a run with the one flag every queued node reads', function () {
    [$user, $project] = searcherWithProject();

    $run = DiscoveryRun::factory()->create([
        'project_id' => $project->id,
        'status' => DiscoveryRunStatus::Running,
    ]);

    $this->actingAs($user)->post(route('discovery-runs.cancel', $run))->assertRedirect();

    expect($run->refresh()->status)->toBe(DiscoveryRunStatus::Aborted)
        ->and($run->finished_at)->not->toBeNull();
});

it('replays one node and reopens the run it belonged to', function () {
    Queue::fake();

    [$user, $project] = searcherWithProject();

    $run = DiscoveryRun::factory()->create([
        'project_id' => $project->id,
        'status' => DiscoveryRunStatus::Exhausted,
        'finished_at' => now(),
    ]);

    $task = DiscoveryTask::factory()->create([
        'project_id' => $project->id,
        'discovery_run_id' => $run->id,
        'kind' => DiscoveryTaskKind::Harvest,
        'status' => DiscoveryTaskStatus::Failed,
        'payload' => ['host' => 'pagesdor.be', 'url' => 'https://pagesdor.be/friteries/namur'],
        'error' => 'HTTP 403',
    ]);

    $this->actingAs($user)->post(route('discovery-tasks.replay', $task))->assertRedirect();

    // Reopened, or the node would read the stop flag and delete itself.
    expect($run->refresh()->status)->toBe(DiscoveryRunStatus::Running)
        ->and($run->finished_at)->toBeNull()
        ->and($task->refresh()->status)->toBe(DiscoveryTaskStatus::Pending);

    Queue::assertPushed(HarvestListing::class);
});
