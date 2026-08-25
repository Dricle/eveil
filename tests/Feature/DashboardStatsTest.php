<?php

use App\Cloud\Models\CreditTransaction;
use App\Models\AgentRun;
use App\Models\Organization;
use App\Models\Project;
use App\Models\User;

function dashboardUser(): array
{
    $organization = Organization::factory()->create();
    $user = User::factory()->create();
    $organization->users()->attach($user, ['role' => 'owner']);
    $project = Project::factory()->for($organization)->create();

    return [$organization, $project, $user];
}

it('shows tokens on self-hosted, never a credit figure', function () {
    config()->set('eveil.edition', 'self');
    [, $project, $user] = dashboardUser();

    AgentRun::factory()->for($project)->create(['tokens_in' => 1000, 'tokens_out' => 200]);
    AgentRun::factory()->for($project)->create(['tokens_in' => 500, 'tokens_out' => 100]);

    $this->actingAs($user)->get(route('dashboard'))
        ->assertInertia(fn ($page) => $page
            ->where('stats.tokens_in', 1500)
            ->where('stats.tokens_out', 300)
            ->missing('stats.credits_spent'));
});

it('shows credits spent on cloud, never a token count', function () {
    config()->set('eveil.edition', 'cloud');
    [$organization, $project, $user] = dashboardUser();

    $run = AgentRun::factory()->for($project)->create(['tokens_in' => 999999, 'tokens_out' => 999999]);
    CreditTransaction::factory()->create([
        'organization_id' => $organization->id,
        'type' => 'debit',
        'credits' => -200,
        'agent_run_id' => $run->id,
    ]);
    // A grant carries no agent_run_id and must never count as spend.
    CreditTransaction::factory()->create([
        'organization_id' => $organization->id,
        'type' => 'grant_trial',
        'credits' => 5000,
    ]);

    $this->actingAs($user)->get(route('dashboard'))
        ->assertInertia(fn ($page) => $page
            ->where('stats.credits_spent', 200)
            ->missing('stats.tokens_in')
            ->missing('stats.tokens_out'));
});

it('scopes cloud credit spend to the current project only', function () {
    config()->set('eveil.edition', 'cloud');
    [$organization, $project, $user] = dashboardUser();
    $otherProject = Project::factory()->for($organization)->create();

    $ownRun = AgentRun::factory()->for($project)->create();
    $otherRun = AgentRun::factory()->for($otherProject)->create();

    CreditTransaction::factory()->create([
        'organization_id' => $organization->id,
        'type' => 'debit',
        'credits' => -50,
        'agent_run_id' => $ownRun->id,
    ]);
    CreditTransaction::factory()->create([
        'organization_id' => $organization->id,
        'type' => 'debit',
        'credits' => -9999,
        'agent_run_id' => $otherRun->id,
    ]);

    $this->actingAs($user)
        ->withSession(['current_project_id' => $project->id])
        ->get(route('dashboard'))
        ->assertInertia(fn ($page) => $page->where('stats.credits_spent', 50));
});
