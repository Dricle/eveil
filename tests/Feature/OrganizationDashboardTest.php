<?php

use App\Enums\MessageDirection;
use App\Enums\ReplyClassification;
use App\Models\Article;
use App\Models\Company;
use App\Models\Message;
use App\Models\Organization;
use App\Models\Project;
use App\Models\User;

it('sums every project of the organization, waiting ones first, and nothing from another one', function () {
    $organization = Organization::factory()->create();
    $user = User::factory()->create();
    $organization->users()->attach($user, ['role' => 'owner']);
    $alpha = Project::factory()->for($organization)->create(['name' => 'Alpha']);
    $beta = Project::factory()->for($organization)->create(['name' => 'Beta']);
    $foreign = Project::factory()->create();

    Company::factory()->count(2)->create(['project_id' => $alpha->id]);
    Company::factory()->create(['project_id' => $beta->id]);
    Company::factory()->count(5)->create(['project_id' => $foreign->id]);

    Message::factory()->count(3)->create(['lead_id' => contactable($alpha, 'a@alpha.test')->id]);
    // Sent before the week: counts in the totals, not in "this week".
    Message::factory()->create(['lead_id' => contactable($alpha, 'old@alpha.test')->id, 'sent_at' => now()->subDays(10)]);
    Article::factory()->count(2)->create(['project_id' => $beta->id]);
    Message::factory()->create(['lead_id' => contactable($beta, 'b@beta.test')->id]);
    Message::factory()->create([
        'lead_id' => contactable($beta, 'c@beta.test')->id,
        'direction' => MessageDirection::Inbound,
        'classification' => ReplyClassification::Interested,
        'sent_at' => null,
        'received_at' => now(),
    ]);
    Message::factory()->count(4)->create(['lead_id' => contactable($foreign, 'x@foreign.test')->id]);

    forProject($alpha);

    $this->actingAs($user)->get(route('organization.dashboard'))
        ->assertInertia(fn ($page) => $page
            ->component('OrganizationDashboard')
            ->has('projects', 2)
            ->where('projects.0.name', 'Beta')
            ->where('projects.0.waiting', 2)
            ->where('projects.0.stats.positive', 1)
            ->where('projects.1.name', 'Alpha')
            ->where('projects.1.waiting', 0)
            ->where('projects.1.stats.companies_found', 2)
            ->where('projects.1.stats.sent', 4)
            ->where('projects.1.week.sent', 3)
            ->where('totals.companies_found', 3)
            ->where('totals.sent', 5)
            ->where('totals.positive', 1)
            ->where('totals.positive_rate', 20)
            ->where('week.sent', 4));
});

it('shows a member only the projects they were granted', function () {
    $organization = Organization::factory()->create();
    $member = User::factory()->create();
    $organization->users()->attach($member, ['role' => 'member']);
    $granted = Project::factory()->for($organization)->create();
    $hidden = Project::factory()->for($organization)->create();
    $granted->users()->attach($member);

    Company::factory()->count(3)->create(['project_id' => $hidden->id]);

    forProject($granted);

    $this->actingAs($member)->get(route('organization.dashboard'))
        ->assertInertia(fn ($page) => $page
            ->has('projects', 1)
            ->where('projects.0.slug', $granted->slug)
            ->where('totals.companies_found', 0));
});
