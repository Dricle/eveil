<?php

use App\Enums\InsightKind;
use App\Models\Insight;
use App\Models\Organization;
use App\Models\Project;
use App\Models\User;

/**
 * @return array{0: User, 1: Project}
 */
function recommender(): array
{
    $user = User::factory()->create();
    Organization::factory()->create()->users()->attach($user, ['role' => 'owner']);

    $project = Project::factory()->for($user->organizations()->sole())->create();
    Insight::factory()->for($project)->recommendation('referral_program')->create();

    forProject($project);

    return [$user, $project];
}

it('marks a recommendation done', function () {
    [$user, $project] = recommender();

    $this->actingAs($user)
        ->put(route('recommendations.status', 'referral_program'), ['status' => 'done'])
        ->assertRedirect();

    expect($project->fresh()->recommendations()[0]['status'])->toBe('done');
});

it('marks a recommendation archived', function () {
    [$user, $project] = recommender();

    $this->actingAs($user)
        ->put(route('recommendations.status', 'referral_program'), ['status' => 'archived'])
        ->assertRedirect();

    expect($project->fresh()->recommendations()[0]['status'])->toBe('archived');
});

it('refuses a status that is not one of the two terminal ones', function () {
    [$user, $project] = recommender();

    $this->actingAs($user)
        ->put(route('recommendations.status', 'referral_program'), ['status' => 'proposed'])
        ->assertSessionHasErrors('status');

    expect($project->fresh()->recommendations()[0]['status'])->toBe('proposed');
});

it('404s on an unknown key', function () {
    [$user] = recommender();

    $this->actingAs($user)
        ->put(route('recommendations.status', 'not_a_real_key'), ['status' => 'done'])
        ->assertNotFound();
});

it('never updates a recommendation on another project', function () {
    [$user] = recommender();
    $someoneElses = Project::factory()->create();
    Insight::factory()->for($someoneElses)->recommendation('referral_program')->create();

    // `actingAs()` resets `URL::defaults` to a project this user can see
    // (`tests/TestCase.php`), so the target project is named explicitly here
    // rather than through `forProject()` - the same reason
    // `KnowledgeBaseTest`'s equivalent 404 test does it this way too.
    $this->actingAs($user)
        ->put(route('recommendations.status', ['project' => $someoneElses->slug, 'key' => 'referral_program']), ['status' => 'done'])
        ->assertNotFound();

    expect($someoneElses->fresh()->recommendations()[0]['status'])->toBe('proposed');
});

it('adds a feature marked done from the dashboard to the key features', function () {
    [$user, $project] = recommender();
    $project->update(['knowledge_base' => ['key_features' => []]]);
    Insight::factory()->for($project)->recommendation('crm_sync', 'Two-way CRM sync', kind: InsightKind::Feature)->create();

    $this->actingAs($user)->put(route('recommendations.status', 'crm_sync'), ['status' => 'done'])->assertRedirect();

    expect($project->fresh()->knowledge_base['key_features'])->toBe(['Two-way CRM sync']);
});
