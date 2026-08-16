<?php

use App\Actions\AnalyzeWebsite;
use App\Enums\AnalysisStatus;
use App\Enums\AnalysisType;
use App\Models\Organization;
use App\Models\Project;
use App\Models\ProjectAnalysis;
use App\Models\User;

function owner(): User
{
    $user = User::factory()->create();
    Organization::factory()->create()->users()->attach($user, ['role' => 'owner']);

    return $user;
}

/**
 * @return array<string, mixed>
 */
function portrait(): array
{
    return [
        'what_it_does' => 'Schedules deliveries for regional wholesalers.',
        'who_it_is_for' => 'Wholesalers running their own vans.',
        'value_proposition' => 'One route plan instead of four spreadsheets.',
        'positioning' => 'Cheaper than the fleet suites, more than a map.',
        'pricing_model' => 'Per vehicle, per month.',
        'key_features' => ['Route planning', 'Proof of delivery'],
        'competitors' => ['Fleetio'],
        'proof_points' => ['300 vans routed daily'],
        'gaps' => ['Whether it does refrigerated loads'],
        'language' => 'en',
        'confidence' => 80,
    ];
}

it('shows the knowledge base on the project page', function () {
    $user = owner();
    $project = Project::factory()->for($user->organizations()->sole())->create(['knowledge_base' => portrait()]);

    ProjectAnalysis::factory()->create([
        'project_id' => $project->id,
        'type' => AnalysisType::Website,
        'status' => AnalysisStatus::Succeeded,
    ]);

    $this->actingAs($user)->get(route('settings.knowledge-base.edit'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('settings/KnowledgeBase')
            ->where('project.knowledge_base.what_it_does', portrait()['what_it_does'])
            ->where('project.edited_by_user', false)
            ->where('project.last_analysis.status', 'succeeded'));
});

it('renders a project whose analysis has not landed yet', function () {
    $user = owner();
    $project = Project::factory()->for($user->organizations()->sole())->create(['knowledge_base' => null]);

    $this->actingAs($user)->get(route('settings.knowledge-base.edit'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('project.knowledge_base', null)
            ->where('project.last_analysis', null));
});

it('saves a correction and splits the list fields on newlines', function () {
    $user = owner();
    $project = Project::factory()->for($user->organizations()->sole())->create(['knowledge_base' => portrait()]);

    $this->actingAs($user)
        ->put(route('settings.knowledge-base.update'), [
            ...portrait(),
            'what_it_does' => 'Corrected by the person who sells it.',
            'key_features' => "Route planning\n  Proof of delivery  \n\nCold chain\n",
        ])
        ->assertRedirect(route('settings.knowledge-base.edit'))
        ->assertSessionHasNoErrors();

    $project->refresh();

    expect($project->knowledge_base['what_it_does'])->toBe('Corrected by the person who sells it.')
        ->and($project->knowledge_base['key_features'])->toBe(['Route planning', 'Proof of delivery', 'Cold chain'])
        ->and($project->knowledge_base_edited_by_user)->toBeTrue()
        // The model's own report on its run survives an edit that never asked
        // the user about it.
        ->and($project->knowledge_base['confidence'])->toBe(80);
});

it('refuses a correction that empties a required field', function () {
    $user = owner();
    $project = Project::factory()->for($user->organizations()->sole())->create(['knowledge_base' => portrait()]);

    $this->actingAs($user)
        ->put(route('settings.knowledge-base.update'), [...portrait(), 'what_it_does' => ''])
        ->assertSessionHasErrors('what_it_does');

    expect($project->fresh()->knowledge_base_edited_by_user)->toBeFalse();
});

it('keeps a correction through a later analysis', function () {
    $user = owner();
    $project = Project::factory()->for($user->organizations()->sole())->create([
        'knowledge_base' => portrait(),
        'knowledge_base_edited_by_user' => true,
    ]);

    // The action decides this, not the controller — a re-analysis reaching the
    // project by any other route must not overwrite a correction either.
    $method = new ReflectionMethod(AnalyzeWebsite::class, 'applyToProject');
    $method->invoke(app(AnalyzeWebsite::class), $project, ['what_it_does' => 'Rewritten by the model.'], collect());

    expect($project->fresh()->knowledge_base['what_it_does'])->toBe(portrait()['what_it_does']);
});

it('only ever writes the knowledge base of the current project', function () {
    $user = owner();
    $own = Project::factory()->for($user->organizations()->sole())->create(['knowledge_base' => portrait()]);
    $someoneElses = Project::factory()->create(['knowledge_base' => portrait()]);

    // There is no project id to tamper with: the session picks the project,
    // and the session only ever holds one this user may see.
    $this->actingAs($user)
        ->withSession(['current_project_id' => $someoneElses->id])
        ->put(route('settings.knowledge-base.update'), [...portrait(), 'what_it_does' => 'Written by a stranger.'])
        ->assertSessionHasNoErrors();

    expect($someoneElses->fresh()->knowledge_base_edited_by_user)->toBeFalse()
        ->and($own->fresh()->knowledge_base['what_it_does'])->toBe('Written by a stranger.');
});
