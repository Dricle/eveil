<?php

use App\Ai\Tools\MarkExistingClients;
use App\Enums\OutreachStatus;
use App\Models\Company;
use App\Models\Lead;
use App\Models\Project;
use Illuminate\Support\Str;
use Laravel\Ai\Tools\Request;

it('marks a CRM customer list as existing clients through the API', function () {
    $project = Project::factory()->create();
    $company = Company::factory()->for($project)->create(['domain' => 'example.org']);
    $token = Str::after($project->organization->createToken('test')->plainTextToken, '|');

    $this->withToken($token)
        ->postJson(route('api.known-clients.store', $project), ['entries' => ['jean@example.com', 'https://example.org']])
        ->assertOk()
        ->assertExactJson(['companies' => 1, 'leads' => 1]);

    expect($company->fresh()->status)->toBe(OutreachStatus::Client)
        ->and(Lead::query()->where('project_id', $project->id)->sole()->status)->toBe(OutreachStatus::Client);

    $this->withToken($token)->postJson(route('api.known-clients.store', $project), ['entries' => []])->assertUnprocessable();
});

it('lets Evie mark existing clients on her own project only', function () {
    $project = Project::factory()->create();
    $other = Project::factory()->create();

    $result = (new MarkExistingClients($project))->handle(new Request(['entries' => ['jean@example.com']]));

    expect($result)->toContain('1 people')
        ->and(Lead::query()->where('project_id', $project->id)->sole()->status)->toBe(OutreachStatus::Client)
        ->and(Lead::query()->where('project_id', $other->id)->exists())->toBeFalse();
});
