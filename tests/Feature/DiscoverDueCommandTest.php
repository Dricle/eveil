<?php

use App\Enums\AutonomyLevel;
use App\Enums\DiscoveryDiagnosis;
use App\Enums\DiscoveryRunStatus;
use App\Jobs\Discovery\PlanDiscovery;
use App\Models\DiscoveryRun;
use App\Models\Lead;
use App\Models\Project;
use App\Models\TargetProfile;
use Illuminate\Support\Facades\Queue;

beforeEach(function () {
    Queue::fake([PlanDiscovery::class]);
});

it('starts a run for every active target profile ready for one', function () {
    $project = Project::factory()->create(['autonomy_level' => AutonomyLevel::SemiAuto]);
    $profile = TargetProfile::factory()->create(['project_id' => $project->id, 'is_active' => true]);
    TargetProfile::factory()->create(['project_id' => $project->id, 'is_active' => false]);

    $this->artisan('eveil:discover-due')->assertSuccessful();

    expect(DiscoveryRun::query()->where('target_profile_id', $profile->id)->count())->toBe(1)
        ->and(DiscoveryRun::query()->count())->toBe(1);

    Queue::assertPushed(PlanDiscovery::class, 1);
});

it('never starts a second run while one is already in flight', function () {
    $profile = TargetProfile::factory()->create(['is_active' => true]);
    DiscoveryRun::factory()->create([
        'project_id' => $profile->project_id,
        'target_profile_id' => $profile->id,
        'status' => DiscoveryRunStatus::Running,
    ]);

    $this->artisan('eveil:discover-due')->assertSuccessful();

    expect(DiscoveryRun::query()->where('target_profile_id', $profile->id)->count())->toBe(1);
    Queue::assertNotPushed(PlanDiscovery::class);
});

it('never re-runs a profile diagnosed as the wrong target', function () {
    $profile = TargetProfile::factory()->create(['is_active' => true]);
    DiscoveryRun::factory()->create([
        'project_id' => $profile->project_id,
        'target_profile_id' => $profile->id,
        'status' => DiscoveryRunStatus::Succeeded,
        'diagnosis' => DiscoveryDiagnosis::BadTargetProfile,
    ]);

    $this->artisan('eveil:discover-due')->assertSuccessful();

    Queue::assertNotPushed(PlanDiscovery::class);
});

it('runs again for a profile whose last run only came up narrow', function () {
    $profile = TargetProfile::factory()->create(['is_active' => true]);
    DiscoveryRun::factory()->create([
        'project_id' => $profile->project_id,
        'target_profile_id' => $profile->id,
        'status' => DiscoveryRunStatus::Succeeded,
        'diagnosis' => DiscoveryDiagnosis::TooNarrow,
    ]);

    $this->artisan('eveil:discover-due')->assertSuccessful();

    Queue::assertPushed(PlanDiscovery::class, 1);
});

it('leaves a supervised project alone', function () {
    $project = Project::factory()->create(['autonomy_level' => AutonomyLevel::Supervised]);
    TargetProfile::factory()->create(['project_id' => $project->id, 'is_active' => true]);

    $this->artisan('eveil:discover-due')->assertSuccessful();

    Queue::assertNotPushed(PlanDiscovery::class);
});

it('stops once the project has reached its daily lead limit', function () {
    $project = Project::factory()->create(['daily_lead_limit' => 1]);
    Lead::factory()->create(['project_id' => $project->id, 'discovered_at' => now()]);
    TargetProfile::factory()->create(['project_id' => $project->id, 'is_active' => true]);

    $this->artisan('eveil:discover-due')->assertSuccessful();

    Queue::assertNotPushed(PlanDiscovery::class);
});

it('resumes the next day once the daily limit resets', function () {
    $project = Project::factory()->create(['daily_lead_limit' => 1]);
    Lead::factory()->create(['project_id' => $project->id, 'discovered_at' => now()->subDay()]);
    TargetProfile::factory()->create(['project_id' => $project->id, 'is_active' => true]);

    $this->artisan('eveil:discover-due')->assertSuccessful();

    Queue::assertPushed(PlanDiscovery::class, 1);
});

it('stops for good once the project has reached its lifetime lead limit', function () {
    $project = Project::factory()->create(['lead_limit' => 1]);
    Lead::factory()->create(['project_id' => $project->id, 'discovered_at' => now()->subYear()]);
    TargetProfile::factory()->create(['project_id' => $project->id, 'is_active' => true]);

    $this->artisan('eveil:discover-due')->assertSuccessful();

    Queue::assertNotPushed(PlanDiscovery::class);
});
