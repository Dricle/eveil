<?php

use App\Enums\DiscoveryRunStatus;
use App\Models\DiscoveryRun;
use App\Models\Organization;
use App\Models\Project;
use App\Models\TargetProfile;
use App\Models\User;

/**
 * The chat panel's job chip reads this the same way every other sidebar
 * badge reads `navCounts`: a plain shared prop, not `Inertia::optional()`,
 * so no partial-reload header dance is needed to test it - just a normal
 * visit.
 */
function chatJobsVisitor(): array
{
    $user = User::factory()->create();
    Organization::factory()->create()->users()->attach($user, ['role' => 'owner']);
    $project = Project::factory()->for($user->organizations()->sole())->create();

    return [$user, $project];
}

it('is null while no project is selected', function () {
    $user = User::factory()->create();

    // account/* is deliberately outside project.require: somebody with no
    // project still has an account, which is what lets this visit render
    // instead of redirecting to projects.create.
    $this->actingAs($user)->get(route('account.profile'))
        ->assertInertia(fn ($page) => $page->where('chatJobs', null));
});

it('is empty with no in-flight discovery run', function () {
    [$user, $project] = chatJobsVisitor();

    $this->actingAs($user)->withSession(['current_project_id' => $project->id])
        ->get(route('dashboard'))
        ->assertInertia(fn ($page) => $page->where('chatJobs', []));
});

it('lists an in-flight discovery run, regardless of who started it', function () {
    [$user, $project] = chatJobsVisitor();
    $profile = TargetProfile::factory()->create(['project_id' => $project->id]);

    $run = DiscoveryRun::factory()->create([
        'project_id' => $project->id,
        'target_profile_id' => $profile->id,
        'status' => DiscoveryRunStatus::Running,
        'started_at' => now(),
    ]);

    $this->actingAs($user)->withSession(['current_project_id' => $project->id])
        ->get(route('dashboard'))
        ->assertInertia(fn ($page) => $page
            ->where('chatJobs.0.type', 'discovery_run')
            ->where('chatJobs.0.id', $run->id)
            ->where('chatJobs.0.status', 'running'));
});

it('drops a run once it reaches a terminal status', function () {
    [$user, $project] = chatJobsVisitor();
    $profile = TargetProfile::factory()->create(['project_id' => $project->id]);

    DiscoveryRun::factory()->create([
        'project_id' => $project->id,
        'target_profile_id' => $profile->id,
        'status' => DiscoveryRunStatus::Succeeded,
        'started_at' => now(),
    ]);

    $this->actingAs($user)->withSession(['current_project_id' => $project->id])
        ->get(route('dashboard'))
        ->assertInertia(fn ($page) => $page->where('chatJobs', []));
});

it('drops a run older than an hour, so a crashed worker never spins the chip forever', function () {
    [$user, $project] = chatJobsVisitor();
    $profile = TargetProfile::factory()->create(['project_id' => $project->id]);

    DiscoveryRun::factory()->create([
        'project_id' => $project->id,
        'target_profile_id' => $profile->id,
        'status' => DiscoveryRunStatus::Running,
        'started_at' => now()->subHours(2),
    ]);

    $this->actingAs($user)->withSession(['current_project_id' => $project->id])
        ->get(route('dashboard'))
        ->assertInertia(fn ($page) => $page->where('chatJobs', []));
});
