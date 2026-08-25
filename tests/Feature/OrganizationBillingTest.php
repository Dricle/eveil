<?php

use App\Cloud\Models\CreditTransaction;
use App\Models\AgentRun;
use App\Models\Organization;
use App\Models\Project;
use App\Models\User;

function billingUser(): array
{
    $organization = Organization::factory()->create();
    $user = User::factory()->create();
    $organization->users()->attach($user, ['role' => 'owner']);
    $project = Project::factory()->for($organization)->create();

    return [$organization, $project, $user];
}

it('breaks credit spend down per project, spend first', function () {
    [$organization, $project, $user] = billingUser();
    $otherProject = Project::factory()->for($organization)->create(['name' => 'Second product']);
    $emptyProject = Project::factory()->for($organization)->create(['name' => 'Untouched']);

    $run = AgentRun::factory()->for($project)->create();
    $otherRun = AgentRun::factory()->for($otherProject)->create();

    CreditTransaction::factory()->create([
        'organization_id' => $organization->id, 'type' => 'debit', 'credits' => -50, 'agent_run_id' => $run->id,
    ]);
    CreditTransaction::factory()->create([
        'organization_id' => $organization->id, 'type' => 'debit', 'credits' => -30, 'agent_run_id' => $run->id,
    ]);
    CreditTransaction::factory()->create([
        'organization_id' => $organization->id, 'type' => 'debit', 'credits' => -200, 'agent_run_id' => $otherRun->id,
    ]);
    // A grant carries no agent_run_id and must never count as spend.
    CreditTransaction::factory()->create([
        'organization_id' => $organization->id, 'type' => 'grant_trial', 'credits' => 5000,
    ]);

    $this->actingAs($user)->get(route('settings.organization.billing.edit'))
        ->assertInertia(fn ($page) => $page
            ->where('creditsByProject.0.name', 'Second product')
            ->where('creditsByProject.0.credits', 200)
            ->where('creditsByProject.1.name', $project->name)
            ->where('creditsByProject.1.credits', 80)
            ->where('creditsByProject.2.name', 'Untouched')
            ->where('creditsByProject.2.credits', 0));
});

it('is not scoped to whichever project is currently selected', function () {
    // agent_runs and target_profiles both carry a project-scoping global
    // scope keyed on the session's current project; the breakdown must cover
    // every project of the organization regardless of which one that is.
    [$organization, $project, $user] = billingUser();
    $otherProject = Project::factory()->for($organization)->create();

    $otherRun = AgentRun::factory()->for($otherProject)->create();
    CreditTransaction::factory()->create([
        'organization_id' => $organization->id, 'type' => 'debit', 'credits' => -75, 'agent_run_id' => $otherRun->id,
    ]);

    $this->actingAs($user)
        ->withSession(['current_project_id' => $project->id])
        ->get(route('settings.organization.billing.edit'))
        ->assertInertia(fn ($page) => $page->has('creditsByProject', 2));
});
