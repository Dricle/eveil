<?php

use App\Enums\CampaignStatus;
use App\Enums\SocialPlatform;
use App\Jobs\GenerateSocialPost;
use App\Models\Campaign;
use App\Models\Company;
use App\Models\Lead;
use App\Models\Organization;
use App\Models\Project;
use App\Models\User;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;

/**
 * The public API. A token belongs to one project, so the guard that matters
 * most is the one every scoped screen has: another project's id is a 404.
 */
function apiToken(Project $project): string
{
    return Str::after($project->createToken('test')->plainTextToken, '|');
}

it('refuses a request without a valid token', function () {
    $this->getJson(route('api.companies.index'))->assertUnauthorized();
    $this->withToken('eveil_nope')->getJson(route('api.companies.index'))->assertUnauthorized();
});

it('lists only the token project companies and 404s on another project', function () {
    $mine = Project::factory()->create();
    $theirs = Project::factory()->create();
    $company = Company::factory()->for($mine)->create();
    $foreign = Company::factory()->for($theirs)->create();

    $this->withToken(apiToken($mine))
        ->getJson(route('api.companies.index'))
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $company->id);

    $this->withToken(apiToken($mine))
        ->getJson(route('api.companies.show', $foreign->id))
        ->assertNotFound();
});

it('adds contacts through the import rules and reports every row', function () {
    $project = Project::factory()->create();
    Lead::factory()->for($project)->create(['email' => 'known@example.com']);

    $this->withToken(apiToken($project))
        ->postJson(route('api.contacts.store'), ['contacts' => [
            ['email' => 'jean@example.com', 'first_name' => 'Jean', 'company_name' => 'Example', 'company_domain' => 'example.com'],
            ['email' => 'known@example.com'],
            ['first_name' => 'Nobody'],
        ]])
        ->assertOk()
        ->assertJsonPath('imported', 1)
        ->assertJsonPath('duplicates', 1)
        ->assertJsonPath('rejected.0.line', 3);

    expect(Lead::withoutGlobalScopes()->where('project_id', $project->id)->where('email', 'jean@example.com')->sole()->company->domain)
        ->toBe('example.com');
});

it('rejects a contact field the import does not know', function () {
    $project = Project::factory()->create();

    $this->withToken(apiToken($project))
        ->postJson(route('api.contacts.store'), ['contacts' => [['email' => 'a@example.com', 'phone' => '123']]])
        ->assertUnprocessable();
});

it('enrols only into a running campaign', function () {
    [, $project] = sender();
    $active = Campaign::factory()->for($project)->create(['status' => CampaignStatus::Active]);
    $draft = Campaign::factory()->for($project)->create(['status' => CampaignStatus::Draft]);
    Lead::factory()->for($project)->create(['company_id' => null]);

    $token = apiToken($project);

    $this->withToken($token)->postJson(route('api.campaigns.enrolments.store', $draft->id))->assertConflict();
    $this->withToken($token)->postJson(route('api.campaigns.enrolments.store', $active->id))
        ->assertOk()
        ->assertJsonPath('enrolled', 1);
});

it('queues a social post for the token project', function () {
    Queue::fake();
    $project = Project::factory()->create();

    $this->withToken(apiToken($project))
        ->postJson(route('api.social-posts.store'), ['platform' => 'bluesky', 'brief' => 'We shipped an API.'])
        ->assertAccepted();

    Queue::assertPushed(GenerateSocialPost::class, fn (GenerateSocialPost $job): bool => $job->project->is($project)
        && $job->platform === SocialPlatform::Bluesky
        && $job->brief === 'We shipped an API.');
});

it('shows a new token once, without the id prefix, and revokes it', function () {
    $user = User::factory()->create();
    $organization = Organization::factory()->create();
    $organization->users()->attach($user, ['role' => 'owner']);
    $project = Project::factory()->for($organization)->create();
    forProject($project);

    $this->actingAs($user)->post(route('settings.api-tokens.store'), ['name' => 'CRM sync'])
        ->assertRedirect(route('settings.api-tokens.index'));

    $secret = session('plainTextToken');
    expect($secret)->toStartWith('eveil_')->not->toContain('|');

    $this->actingAs($user)->delete(route('settings.api-tokens.destroy', $project->tokens()->sole()->id))
        ->assertRedirect();

    expect($project->tokens()->count())->toBe(0);
});

it('cannot revoke another project token', function () {
    $user = User::factory()->create();
    $organization = Organization::factory()->create();
    $organization->users()->attach($user, ['role' => 'owner']);
    $project = Project::factory()->for($organization)->create();
    $foreign = Project::factory()->create();
    $foreign->createToken('theirs');
    forProject($project);

    $this->actingAs($user)->delete(route('settings.api-tokens.destroy', $foreign->tokens()->sole()->id))
        ->assertNotFound();

    expect($foreign->tokens()->count())->toBe(1);
});
