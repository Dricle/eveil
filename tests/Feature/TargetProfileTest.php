<?php

use App\Ai\Agents\TargetProfileDeriver;
use App\Enums\AgentRunStatus;
use App\Enums\TargetProfileSource;
use App\Enums\TargetProfileType;
use App\Jobs\DeriveTargets;
use App\Models\AgentRun;
use App\Models\Organization;
use App\Models\Project;
use App\Models\TargetProfile;
use App\Models\User;
use Illuminate\Support\Facades\Queue;
use RuntimeException;

function targeter(): User
{
    $user = User::factory()->create();
    Organization::factory()->create()->users()->attach($user, ['role' => 'owner']);

    return $user;
}

/**
 * @return array<string, mixed>
 */
function profileForm(array $overrides = []): array
{
    return [
        'name' => 'Regional wholesalers running their own vans',
        'type' => 'customer',
        'is_active' => true,
        'rationale' => 'They plan routes by hand and lose an hour a day to it.',
        'company_size' => '10 to 50 vehicles',
        'estimated_market_size' => 'A few thousand across the region.',
        'sectors' => "food wholesale\nbuilding supplies",
        'geography' => 'Benelux',
        'job_titles' => 'operations manager',
        'technologies' => '',
        'trigger_signals' => 'hiring drivers',
        'search_queries' => 'grossiste alimentaire livraison',
        ...$overrides,
    ];
}

it('lists the profiles of the current project', function () {
    $user = targeter();
    $project = Project::factory()->for($user->organizations()->sole())->create();

    TargetProfile::factory()->create(['project_id' => $project->id, 'name' => 'Independent physiotherapy practices']);

    $this->actingAs($user)->get(route('target-profiles.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('TargetProfiles')
            ->where('profiles.0.name', 'Independent physiotherapy practices')
            ->where('profiles.0.type', 'customer')
            ->where('analyzed', false));
});

it('never shows a profile belonging to another project', function () {
    $user = targeter();
    Project::factory()->for($user->organizations()->sole())->create();

    TargetProfile::factory()->create(['name' => 'Somebody else\'s market']);

    $this->actingAs($user)->get(route('target-profiles.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->where('profiles', []));
});

it('creates a profile from the form, one list item per line', function () {
    $user = targeter();
    $project = Project::factory()->for($user->organizations()->sole())->create();

    $this->actingAs($user)
        ->post(route('target-profiles.store'), profileForm())
        ->assertRedirect(route('target-profiles.index'));

    $profile = TargetProfile::query()->withoutGlobalScopes()->sole();

    expect($profile->project_id)->toBe($project->id)
        ->and($profile->source)->toBe(TargetProfileSource::Human)
        ->and($profile->type)->toBe(TargetProfileType::Customer)
        ->and($profile->criteria['sectors'])->toBe(['food wholesale', 'building supplies'])
        ->and($profile->criteria['technologies'])->toBe([]);
});

it('keeps what the model reported about itself when the user corrects a profile', function () {
    $user = targeter();
    $project = Project::factory()->for($user->organizations()->sole())->create();

    $profile = TargetProfile::factory()->create([
        'project_id' => $project->id,
        'source' => TargetProfileSource::Agent,
        'criteria' => ['sectors' => ['web agencies'], 'confidence' => 72],
    ]);

    $this->actingAs($user)
        ->put(route('target-profiles.update', $profile), profileForm([
            'type' => 'partner',
            'is_active' => false,
        ]))
        ->assertRedirect(route('target-profiles.index'));

    $profile->refresh();

    expect($profile->criteria['confidence'])->toBe(72)
        ->and($profile->criteria['sectors'])->toBe(['food wholesale', 'building supplies'])
        ->and($profile->type)->toBe(TargetProfileType::Partner)
        ->and($profile->is_active)->toBeFalse()
        // A corrected profile is the user's, which is what keeps the next
        // derivation from throwing it away.
        ->and($profile->source)->toBe(TargetProfileSource::Human);
});

it('refuses to save a profile with no name', function () {
    $user = targeter();
    Project::factory()->for($user->organizations()->sole())->create();

    $this->actingAs($user)
        ->post(route('target-profiles.store'), profileForm(['name' => '']))
        ->assertSessionHasErrors('name');
});

it('does not let a project delete another project\'s profile', function () {
    $user = targeter();
    Project::factory()->for($user->organizations()->sole())->create();

    $other = TargetProfile::factory()->create();

    $this->actingAs($user)
        ->delete(route('target-profiles.destroy', $other))
        ->assertNotFound();

    expect(TargetProfile::query()->withoutGlobalScopes()->count())->toBe(1);
});

it('deletes a profile', function () {
    $user = targeter();
    $project = Project::factory()->for($user->organizations()->sole())->create();

    $profile = TargetProfile::factory()->create(['project_id' => $project->id]);

    $this->actingAs($user)
        ->delete(route('target-profiles.destroy', $profile))
        ->assertRedirect(route('target-profiles.index'));

    expect(TargetProfile::query()->withoutGlobalScopes()->count())->toBe(0);
});

it('queues a derivation for the current project', function () {
    Queue::fake();

    $user = targeter();
    $project = Project::factory()->for($user->organizations()->sole())->create();

    $this->actingAs($user)
        ->post(route('target-profiles.derive'))
        ->assertRedirect(route('target-profiles.index'));

    Queue::assertPushed(DeriveTargets::class, fn (DeriveTargets $job): bool => $job->project->is($project));

    // The row is opened before the job is queued, so the page the user lands on
    // already says something is happening — no worker has run yet.
    $run = AgentRun::query()->withoutGlobalScopes()->sole();

    expect($run->agent)->toBe('target-profile-deriver')
        ->and($run->status)->toBe(AgentRunStatus::Pending);

    $this->actingAs($user)->get(route('target-profiles.index'))
        ->assertInertia(fn ($page) => $page->where('deriving', true));
});

it('stops reporting a derivation once the run is finished', function () {
    $user = targeter();
    $project = Project::factory()->for($user->organizations()->sole())->create();

    AgentRun::factory()->create([
        'project_id' => $project->id,
        'agent' => 'target-profile-deriver',
        'status' => AgentRunStatus::Succeeded,
    ]);

    $this->actingAs($user)->get(route('target-profiles.index'))
        ->assertInertia(fn ($page) => $page
            ->where('deriving', false)
            ->where('derivationError', null));
});

it('stops believing a run that has been pending far too long', function () {
    $user = targeter();
    $project = Project::factory()->for($user->organizations()->sole())->create();

    // Nothing drained the queue: a spinner that never stops is worse than
    // saying the work is not running.
    AgentRun::factory()->create([
        'project_id' => $project->id,
        'agent' => 'target-profile-deriver',
        'status' => AgentRunStatus::Pending,
        'created_at' => now()->subHour(),
    ]);

    $this->actingAs($user)->get(route('target-profiles.index'))
        ->assertInertia(fn ($page) => $page->where('deriving', false));
});

it('reports a failed derivation instead of falling silent', function () {
    Queue::fake();

    $user = targeter();
    $project = Project::factory()->for($user->organizations()->sole())->create();

    $run = AgentRun::factory()->create([
        'project_id' => $project->id,
        'agent' => 'target-profile-deriver',
        'status' => AgentRunStatus::Pending,
    ]);

    (new DeriveTargets($project, $run))->failed(new RuntimeException('The provider refused the request.'));

    $this->actingAs($user)->get(route('target-profiles.index'))
        ->assertInertia(fn ($page) => $page
            ->where('deriving', false)
            ->where('derivationError', 'The provider refused the request.'));
});

it('claims the queued run instead of opening a second one', function () {
    $user = targeter();
    $project = Project::factory()->for($user->organizations()->sole())->create(['knowledge_base' => ['what_it_does' => 'Routes vans.']]);

    $run = AgentRun::factory()->create([
        'project_id' => $project->id,
        'agent' => 'target-profile-deriver',
        'status' => AgentRunStatus::Pending,
    ]);

    TargetProfileDeriver::fake([['profiles' => [['name' => 'Regional wholesalers', 'sectors' => ['food wholesale']]]]]);

    // The test queue is synchronous, so this runs the job here and now.
    DeriveTargets::dispatch($project, $run);

    expect(AgentRun::query()->withoutGlobalScopes()->count())->toBe(1)
        ->and($run->refresh()->status)->toBe(AgentRunStatus::Succeeded)
        ->and(TargetProfile::query()->withoutGlobalScopes()->sole()->name)->toBe('Regional wholesalers');
});
