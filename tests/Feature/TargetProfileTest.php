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

function pendingDerivation(Project $project): AgentRun
{
    return AgentRun::factory()->create([
        'project_id' => $project->id,
        'agent' => 'target-profile-deriver',
        'status' => AgentRunStatus::Pending,
    ]);
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

it('opens the section on a profile, since the profiles are the navigation', function () {
    $user = targeter();
    $project = Project::factory()->for($user->organizations()->sole())->create();

    $profile = TargetProfile::factory()->create([
        'project_id' => $project->id,
        'name' => 'Independent physiotherapy practices',
    ]);

    $this->actingAs($user)->get(route('targets.index'))
        ->assertRedirect(route('targets.show', $profile));

    $this->actingAs($user)->get(route('targets.show', $profile))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('targets/Profile')
            ->where('profile.name', 'Independent physiotherapy practices')
            // Shared, because the profile list is this section's navigation.
            ->where('profiles.0.name', 'Independent physiotherapy practices')
            ->where('profiles.0.type', 'customer')
            ->where('analyzed', false));
});

it('offers to derive when there is nothing to show', function () {
    $user = targeter();
    Project::factory()->for($user->organizations()->sole())->create();

    $this->actingAs($user)->get(route('targets.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('targets/Empty')->where('profiles', []));
});

it('never shows a profile belonging to another project', function () {
    $user = targeter();
    Project::factory()->for($user->organizations()->sole())->create();

    $other = TargetProfile::factory()->create(['name' => 'Somebody else\'s market']);

    $this->actingAs($user)->get(route('targets.index'))
        ->assertInertia(fn ($page) => $page->component('targets/Empty')->where('profiles', []));

    $this->actingAs($user)->get(route('targets.show', $other))->assertNotFound();
});

it('creates a profile from the form, one list item per line', function () {
    $user = targeter();
    $project = Project::factory()->for($user->organizations()->sole())->create();

    $profile = TargetProfile::query()->withoutGlobalScopes();

    $this->actingAs($user)
        ->post(route('targets.store'), profileForm())
        ->assertRedirect(route('targets.show', $profile->sole()));

    $profile = $profile->sole();

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
        ->put(route('targets.update', $profile), profileForm([
            'type' => 'partner',
            'is_active' => false,
        ]))
        ->assertRedirect(route('targets.show', $profile));

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
        ->post(route('targets.store'), profileForm(['name' => '']))
        ->assertSessionHasErrors('name');
});

it('does not let a project delete another project\'s profile', function () {
    $user = targeter();
    Project::factory()->for($user->organizations()->sole())->create();

    $other = TargetProfile::factory()->create();

    $this->actingAs($user)
        ->delete(route('targets.destroy', $other))
        ->assertNotFound();

    expect(TargetProfile::query()->withoutGlobalScopes()->count())->toBe(1);
});

it('deletes a profile', function () {
    $user = targeter();
    $project = Project::factory()->for($user->organizations()->sole())->create();

    $profile = TargetProfile::factory()->create(['project_id' => $project->id]);

    $this->actingAs($user)
        ->delete(route('targets.destroy', $profile))
        ->assertRedirect(route('targets.index'));

    expect(TargetProfile::query()->withoutGlobalScopes()->count())->toBe(0);
});

it('queues a derivation for the current project', function () {
    Queue::fake();

    $user = targeter();
    $project = Project::factory()->for($user->organizations()->sole())->create();

    $this->actingAs($user)
        ->from(route('targets.index'))
        ->post(route('targets.derive'))
        ->assertRedirect(route('targets.index'));

    Queue::assertPushed(DeriveTargets::class, fn (DeriveTargets $job): bool => $job->project->is($project));

    // The row is opened before the job is queued, so the page the user lands on
    // already says something is happening — no worker has run yet.
    $run = AgentRun::query()->withoutGlobalScopes()->sole();

    expect($run->agent)->toBe('target-profile-deriver')
        ->and($run->status)->toBe(AgentRunStatus::Pending);

    $this->actingAs($user)->get(route('targets.index'))
        ->assertInertia(fn ($page) => $page->where('deriving', true));
});

it('adds to the profiles by default, and only replaces when asked', function () {
    Queue::fake();

    $user = targeter();
    Project::factory()->for($user->organizations()->sole())->create();

    $this->actingAs($user)->from(route('targets.index'))->post(route('targets.derive'));

    Queue::assertPushed(DeriveTargets::class, fn (DeriveTargets $job): bool => $job->replace === false);

    $this->actingAs($user)->from(route('targets.index'))->post(route('targets.derive'), ['replace' => true]);

    Queue::assertPushed(DeriveTargets::class, fn (DeriveTargets $job): bool => $job->replace === true);
});

it('keeps the profiles already derived unless replacing was asked for', function () {
    $user = targeter();
    $project = Project::factory()->for($user->organizations()->sole())->create([
        'knowledge_base' => ['what_it_does' => 'Routes vans.'],
    ]);

    $kept = TargetProfile::factory()->create([
        'project_id' => $project->id,
        'name' => 'Derived earlier',
        'source' => TargetProfileSource::Agent,
    ]);

    TargetProfileDeriver::fake([
        ['profiles' => [['name' => 'Newly derived', 'sectors' => ['food wholesale']]]],
        ['profiles' => [['name' => 'After the replacement', 'sectors' => ['food wholesale']]]],
    ]);

    // The test queue is synchronous, so each dispatch runs the job here and now.
    DeriveTargets::dispatch($project, pendingDerivation($project), replace: false);

    expect(TargetProfile::query()->withoutGlobalScopes()->pluck('name')->all())
        ->toBe(['Derived earlier', 'Newly derived']);

    DeriveTargets::dispatch($project, pendingDerivation($project), replace: true);

    // Both of the agent's own profiles went; a human one would have survived.
    expect(TargetProfile::query()->withoutGlobalScopes()->pluck('name')->all())
        ->toBe(['After the replacement'])
        ->and($kept->exists())->toBeTrue()
        ->and(TargetProfile::query()->withoutGlobalScopes()->find($kept->id))->toBeNull();
});

it('stops reporting a derivation once the run is finished', function () {
    $user = targeter();
    $project = Project::factory()->for($user->organizations()->sole())->create();

    AgentRun::factory()->create([
        'project_id' => $project->id,
        'agent' => 'target-profile-deriver',
        'status' => AgentRunStatus::Succeeded,
    ]);

    $this->actingAs($user)->get(route('targets.index'))
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

    $this->actingAs($user)->get(route('targets.index'))
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

    $this->actingAs($user)->get(route('targets.index'))
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

it('keeps the two angles a partner profile is written to on', function () {
    $user = targeter();
    $project = Project::factory()->for($user->organizations()->sole())->create();

    $profile = TargetProfile::factory()->create(['project_id' => $project->id]);

    $this->actingAs($user)
        ->put(route('targets.update', $profile), profileForm([
            'type' => 'partner',
            'access_angle' => 'Invoices every one of them monthly.',
            'partnership_angle' => 'Their clients stop calling them about the same problem.',
        ]))
        ->assertRedirect(route('targets.show', $profile));

    expect($profile->refresh()->criteria['partnership_angle'])
        ->toBe('Their clients stop calling them about the same problem.');
});
