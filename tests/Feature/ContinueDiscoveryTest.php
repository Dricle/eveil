<?php

use App\Actions\ContinueDiscovery;
use App\Enums\TargetProfileSource;
use App\Jobs\Discovery\PlanDiscovery;
use App\Models\DiscoveryRun;
use App\Models\TargetProfile;
use App\Support\CurrentProject;
use App\Support\Settings;
use Illuminate\Support\Facades\Queue;

/**
 * The confidence floor is a separate concern from `is_active`: a caller other
 * than `DeriveTargetProfiles` could set `is_active` directly (the future
 * mid-discovery "propose a profile" job this defers to), so the gate has to
 * hold here too, independent of how the flag got set.
 */
beforeEach(function () {
    Queue::fake([PlanDiscovery::class]);
});

function startedRuns(TargetProfile $profile): int
{
    return app(CurrentProject::class)->run(
        $profile->project,
        fn (): int => app(ContinueDiscovery::class)->handle($profile->project),
    );
}

it('does not automatically run an agent profile below the confidence floor', function () {
    app(Settings::class)->set('discovery', [...app(Settings::class)->array('discovery'), 'min_profile_confidence' => 60]);

    $profile = TargetProfile::factory()->create([
        'source' => TargetProfileSource::Agent,
        'is_active' => true,
        'criteria' => ['confidence' => 20],
    ]);

    expect(startedRuns($profile))->toBe(0)
        ->and(DiscoveryRun::count())->toBe(0);
});

it('automatically runs an agent profile at or above the confidence floor', function () {
    app(Settings::class)->set('discovery', [...app(Settings::class)->array('discovery'), 'min_profile_confidence' => 60]);

    $profile = TargetProfile::factory()->create([
        'source' => TargetProfileSource::Agent,
        'is_active' => true,
        'criteria' => ['confidence' => 60],
    ]);

    expect(startedRuns($profile))->toBe(1)
        ->and(DiscoveryRun::where('target_profile_id', $profile->id)->count())->toBe(1);
});

it('never gates a human-authored profile on confidence', function () {
    app(Settings::class)->set('discovery', [...app(Settings::class)->array('discovery'), 'min_profile_confidence' => 60]);

    $profile = TargetProfile::factory()->create([
        'source' => TargetProfileSource::Human,
        'is_active' => true,
        'criteria' => ['confidence' => 5],
    ]);

    expect(startedRuns($profile))->toBe(1);
});

it('never gates an agent profile that reported no confidence at all', function () {
    app(Settings::class)->set('discovery', [...app(Settings::class)->array('discovery'), 'min_profile_confidence' => 60]);

    $profile = TargetProfile::factory()->create([
        'source' => TargetProfileSource::Agent,
        'is_active' => true,
        'criteria' => ['sectors' => ['friteries']],
    ]);

    expect(startedRuns($profile))->toBe(1);
});
