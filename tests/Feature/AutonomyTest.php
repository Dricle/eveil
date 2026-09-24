<?php

use App\Enums\AutonomyLevel;
use App\Models\Organization;
use App\Models\Project;
use App\Models\User;

function autonomySetup(): array
{
    $organization = Organization::factory()->create();
    $user = User::factory()->create();
    $organization->users()->attach($user, ['role' => 'owner']);
    $project = Project::factory()->for($organization)->create();

    return [$user, $project];
}

it('shows each channel\'s own setting', function () {
    [$user, $project] = autonomySetup();

    $this->actingAs($user)
        ->get(route('settings.autonomy.edit'))
        ->assertInertia(fn ($page) => $page
            ->component('settings/Autonomy')
            ->where('emailAutonomyLevel', 'semi_auto')
            // Never inherits the email setting: a new channel starts asking.
            ->where('linkedinAutonomyLevel', 'supervised'));
});

it('saves email and LinkedIn autonomy independently', function () {
    [$user, $project] = autonomySetup();

    $this->actingAs($user)
        ->putJson(route('settings.autonomy.update'), [
            'email_autonomy_level' => 'supervised',
            'linkedin_autonomy_level' => 'autonomous',
        ])
        ->assertSessionHasNoErrors();

    expect($project->fresh()->email_autonomy_level)->toBe(AutonomyLevel::Supervised)
        ->and($project->fresh()->linkedin_autonomy_level)->toBe(AutonomyLevel::Autonomous);
});

it('refuses semi-auto for LinkedIn, which has no step for it to hand over', function () {
    [$user, $project] = autonomySetup();

    $this->actingAs($user)
        ->putJson(route('settings.autonomy.update'), [
            'email_autonomy_level' => 'autonomous',
            'linkedin_autonomy_level' => 'semi_auto',
        ])
        ->assertJsonValidationErrors('linkedin_autonomy_level');

    expect($project->fresh()->email_autonomy_level)->toBe(AutonomyLevel::SemiAuto);
});

it('refuses a level that does not exist', function () {
    [$user, $project] = autonomySetup();

    $this->actingAs($user)
        ->putJson(route('settings.autonomy.update'), [
            'email_autonomy_level' => 'whatever',
            'linkedin_autonomy_level' => 'supervised',
        ])
        ->assertJsonValidationErrors('email_autonomy_level');
});
