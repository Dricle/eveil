<?php

use App\Models\Organization;
use App\Models\Project;
use App\Models\User;

test('returns a successful response', function () {
    $user = User::factory()->create();
    $organization = Organization::factory()->create();
    $organization->users()->attach($user, ['role' => 'owner']);
    Project::factory()->for($organization)->create();

    $response = $this->actingAs($user)->get(route('dashboard'));

    $response->assertOk();
});
