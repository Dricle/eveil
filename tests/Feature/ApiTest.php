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
 * The public API. A token belongs to an organization and reaches its
 * projects, named in the URL, so the guards that matter most are another
 * organization's project (404) and another project's id inside the right
 * URL (404 too).
 */
function apiToken(Project $project): string
{
    return Str::after($project->organization->createToken('test')->plainTextToken, '|');
}

it('refuses a request without a valid token', function () {
    $this->getJson(route('api.projects.index'))->assertUnauthorized();
    $this->withToken('eveil_nope')->getJson(route('api.projects.index'))->assertUnauthorized();
});

it('lists the token organization projects only', function () {
    $project = Project::factory()->create();
    $sibling = Project::factory()->for($project->organization)->create();
    Project::factory()->create();

    $this->withToken(apiToken($project))
        ->getJson(route('api.projects.index'))
        ->assertOk()
        ->assertJsonCount(2, 'data')
        ->assertJsonPath('data.*.slug', collect([$project, $sibling])->sortBy('name')->pluck('slug')->values()->all());
});

it('reads one project at a time and 404s outside the organization or the project', function () {
    $mine = Project::factory()->create();
    $sibling = Project::factory()->for($mine->organization)->create();
    $theirs = Project::factory()->create();
    $company = Company::factory()->for($mine)->create();
    $siblingCompany = Company::factory()->for($sibling)->create();
    $token = apiToken($mine);

    $this->withToken($token)
        ->getJson(route('api.companies.index', $mine))
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $company->id);

    $this->withToken($token)->getJson(route('api.companies.show', [$mine, $siblingCompany->id]))->assertNotFound();
    $this->withToken($token)->getJson(route('api.companies.index', $theirs))->assertNotFound();
    $this->withToken($token)->getJson('/api/v1/projects/no-such-project/companies')->assertNotFound();
});

it('adds contacts through the import rules and reports every row', function () {
    $project = Project::factory()->create();
    Lead::factory()->for($project)->create(['email' => 'known@example.com']);

    $this->withToken(apiToken($project))
        ->postJson(route('api.contacts.store', $project), ['contacts' => [
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
        ->postJson(route('api.contacts.store', $project), ['contacts' => [['email' => 'a@example.com', 'phone' => '123']]])
        ->assertUnprocessable();
});

it('enrols only into a running campaign', function () {
    [, $project] = sender();
    $active = Campaign::factory()->for($project)->create(['status' => CampaignStatus::Active]);
    $draft = Campaign::factory()->for($project)->create(['status' => CampaignStatus::Draft]);
    Lead::factory()->for($project)->create(['company_id' => null]);

    $token = apiToken($project);

    $this->withToken($token)->postJson(route('api.campaigns.enrolments.store', [$project, $draft->id]))->assertConflict();
    $this->withToken($token)->postJson(route('api.campaigns.enrolments.store', [$project, $active->id]))
        ->assertOk()
        ->assertJsonPath('enrolled', 1);
});

it('queues a social post for the token project', function () {
    Queue::fake();
    $project = Project::factory()->create();

    $this->withToken(apiToken($project))
        ->postJson(route('api.social-posts.store', $project), ['platform' => 'bluesky', 'brief' => 'We shipped an API.'])
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

    $this->actingAs($user)->delete(route('settings.api-tokens.destroy', $organization->tokens()->sole()->id))
        ->assertRedirect();

    expect($organization->tokens()->count())->toBe(0);
});

it('cannot revoke another organization token', function () {
    $user = User::factory()->create();
    $organization = Organization::factory()->create();
    $organization->users()->attach($user, ['role' => 'owner']);
    $project = Project::factory()->for($organization)->create();
    $foreign = Organization::factory()->create();
    $foreign->createToken('theirs');
    forProject($project);

    $this->actingAs($user)->delete(route('settings.api-tokens.destroy', $foreign->tokens()->sole()->id))
        ->assertNotFound();

    expect($foreign->tokens()->count())->toBe(1);
});

it('lets only owners and admins create or revoke a token', function () {
    $member = User::factory()->create();
    $organization = Organization::factory()->create();
    $organization->users()->attach($member, ['role' => 'member']);
    $project = Project::factory()->for($organization)->create();
    $project->users()->attach($member);
    $organization->createToken('existing');
    forProject($project);

    $this->actingAs($member)->post(route('settings.api-tokens.store'), ['name' => 'Mine'])->assertForbidden();
    $this->actingAs($member)->delete(route('settings.api-tokens.destroy', $organization->tokens()->sole()->id))->assertForbidden();

    expect($organization->tokens()->count())->toBe(1);
});
