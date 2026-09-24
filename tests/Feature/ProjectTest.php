<?php

use App\Jobs\AnalyzeProject;
use App\Models\Company;
use App\Models\Lead;
use App\Models\Organization;
use App\Models\Project;
use App\Models\TargetProfile;
use App\Models\User;
use App\Support\Settings;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;

function member(): User
{
    $user = User::factory()->create();
    Organization::factory()->create()->users()->attach($user, ['role' => 'owner']);

    return $user;
}

function reachable(): void
{
    Http::fake([
        '*/robots.txt' => Http::response('', 404),
        '*' => Http::response('<!doctype html><html lang="en"><head><title>Acme</title></head><body><p>Acme sells things.</p></body></html>'),
    ]);
}

beforeEach(function () {
    app(Settings::class)->set('crawl.delay_ms', 0);
    Queue::fake();
});

it('sends a user with no project to the create screen', function () {
    $this->get(route('app.home'))->assertRedirect(route('login'));

    $this->actingAs(member())->get(route('app.home'))->assertRedirect(route('projects.create'));
});

it('opens straight into a project once one exists', function () {
    $user = member();
    Project::factory()->for($user->organizations()->sole())->create(['name' => 'Acme']);

    $this->actingAs($user)->get(route('dashboard'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->where('currentProject.name', 'Acme'));
});

it('prefills the create screen with a pasted URL once, then forgets it', function () {
    $this->withSession(['pending_project_url' => 'https://acme.test'])
        ->actingAs(member())
        ->get(route('projects.create'))
        ->assertInertia(fn ($page) => $page->where('prefillUrl', 'https://acme.test'));

    // Pulled, not just read: a second visit - the auto-submit failing
    // validation and reloading the screen, say - must not resurrect it and
    // loop the auto-submit forever.
    $this->get(route('projects.create'))
        ->assertInertia(fn ($page) => $page->where('prefillUrl', null));
});

it('creates a project, starts its analysis and selects it', function () {
    reachable();

    $response = $this->actingAs(member())
        ->post(route('projects.store'), ['name' => 'Acme', 'url' => 'acme.test'])
        ->assertSessionHasNoErrors();

    $project = Project::sole();

    // Into the guided run rather than the dashboard: the site is being read
    // at this moment, and watching that happen is the whole first
    // impression. Named explicitly: `projects.store` carries no project of
    // its own to have bound a `URL::defaults()` fallback from.
    $response->assertRedirect(route('onboarding', ['project' => $project->slug]));

    // The scheme is added for the user rather than demanded from them.
    expect($project->url)->toBe('https://acme.test/');
    expect(session('current_project_id'))->toBe($project->id);

    Queue::assertPushed(AnalyzeProject::class, fn (AnalyzeProject $job): bool => $job->project->is($project));
});

it('refuses an address nothing answers at', function () {
    Http::fake(['*' => Http::response('', 500)]);

    $this->actingAs(member())
        ->post(route('projects.store'), ['name' => 'Acme', 'url' => 'acme.test'])
        ->assertSessionHasErrors('url');

    expect(Project::query()->exists())->toBeFalse();
    Queue::assertNothingPushed();
});

it('re-analyses only when the address changes', function () {
    reachable();

    $user = member();
    $project = Project::factory()->for($user->organizations()->sole())->create(['url' => 'https://acme.test/']);

    $this->actingAs($user)
        ->put(route('settings.project.update'), ['name' => 'Renamed', 'url' => 'https://acme.test/'])
        ->assertSessionHasNoErrors();

    expect($project->fresh()->name)->toBe('Renamed');
    Queue::assertNothingPushed();

    $this->actingAs($user)
        ->put(route('settings.project.update'), ['name' => 'Renamed', 'url' => 'https://acme.test/en'])
        ->assertSessionHasNoErrors();

    Queue::assertPushed(AnalyzeProject::class);
});

it('no longer accepts writing instructions on the project form itself', function () {
    reachable();

    $user = member();
    $project = Project::factory()->for($user->organizations()->sole())->create(['url' => 'https://acme.test/']);

    // Moved to its own screen/route (`settings.ai-instructions.emails.update`,
    // see `ProjectTest`'s sibling `AiInstructionsControllerTest`): submitting
    // it here is silently ignored, not an error, since it is simply not one
    // of this request's rules any more.
    $this->actingAs($user)
        ->put(route('settings.project.update'), [
            'name' => $project->name,
            'url' => 'https://acme.test/',
            'prompt_instructions' => 'Write in French. Never use emoji.',
        ])
        ->assertSessionHasNoErrors();

    expect($project->fresh()->prompt_instructions)->not->toBe('Write in French. Never use emoji.');
});

it('deletes the current project and falls back to the next one', function () {
    $user = member();
    $organization = $user->organizations()->sole();
    $deleted = Project::factory()->for($organization)->create(['name' => 'Aaa']);
    $kept = Project::factory()->for($organization)->create(['name' => 'Bbb']);

    // 'Aaa' sorts first, so it is what `actingAs()` lands on unasked.
    $this->actingAs($user)->delete(route('settings.project.destroy'))->assertRedirect(route('app.home'));

    expect(Project::query()->whereKey($deleted->getKey())->exists())->toBeFalse();

    $this->actingAs($user)->get(route('dashboard'))
        ->assertInertia(fn ($page) => $page->where('currentProject.id', $kept->id));
});

it('deletes every company for the current project, scoped away from another project\'s', function () {
    $user = member();
    $project = Project::factory()->for($user->organizations()->sole())->create();
    $company = Company::factory()->create(['project_id' => $project->id]);
    $foreign = Company::factory()->create();

    $this->actingAs($user)->delete(route('settings.companies.destroy-all'))->assertRedirect();

    // fresh() bypasses the `BelongsToProject` scope (it queries without
    // scopes), which matters here: the request above left `CurrentProject`
    // pointed at $project for the rest of this test process, so a plain
    // `Company::query()` below would silently scope $foreign out too and
    // read as "deleted" whether or not it actually was.
    expect($company->fresh())->toBeNull()
        ->and($foreign->fresh())->not->toBeNull();
});

it('deletes every lead for the current project, scoped away from another project\'s', function () {
    $user = member();
    $project = Project::factory()->for($user->organizations()->sole())->create();
    $lead = Lead::factory()->create(['project_id' => $project->id]);
    $foreign = Lead::factory()->create();

    $this->actingAs($user)->delete(route('settings.contacts.destroy-all'))->assertRedirect();

    expect($lead->fresh())->toBeNull()
        ->and($foreign->fresh())->not->toBeNull();
});

it('404s on a project outside the user\'s organizations, named directly in the URL', function () {
    $stranger = member();
    $someoneElses = Project::factory()->create();

    // No switcher to tamper with any more: the URL itself is the only thing
    // that says which project a request acts on, so this is the whole
    // access-control surface - `Response::denyAsNotFound()` in
    // `ProjectPolicy::view()`, enforced by `SetCurrentProject` for every
    // route under `{project:slug}`.
    $this->actingAs($stranger)
        ->get(route('dashboard', ['project' => $someoneElses->slug]))
        ->assertNotFound();
});

it('updates the "last visited" session hint from ordinary navigation, with no switch action of its own', function () {
    $user = member();
    $organization = $user->organizations()->sole();
    $first = Project::factory()->for($organization)->create(['name' => 'Aaa']);
    $second = Project::factory()->for($organization)->create(['name' => 'Bbb']);

    // Visiting a project's URL is the whole "switch": `SetCurrentProject`
    // updates the hint as a side effect of resolving whatever project the
    // request already named, not through a dedicated endpoint.
    $this->actingAs($user)->get(route('dashboard', ['project' => $first->slug]));
    expect(session('current_project_id'))->toBe($first->id);

    $this->actingAs($user)->get(route('dashboard', ['project' => $second->slug]));
    expect(session('current_project_id'))->toBe($second->id);
});

it('lets a user with no project still reach their account', function () {
    $this->actingAs(member())->get(route('account.profile'))->assertOk();
});

it('keeps the sidebar\'s current project on account pages, though the route is outside {project:slug}', function () {
    $user = member();
    $project = Project::factory()->for($user->organizations()->sole())->create();

    // `project.set` never runs on `account.*` - deliberately, a projectless
    // user still has an account - but a user who DOES have one must not lose
    // the sidebar's project switcher just because they clicked into Account.
    $this->actingAs($user)->get(route('account.profile'))
        ->assertInertia(fn ($page) => $page->where('currentProject.id', $project->id));
});

it('scopes the sidebar badge counts to the displayed project on account pages, not every project', function () {
    $user = member();
    $organization = $user->organizations()->sole();
    $own = Project::factory()->for($organization)->create(['name' => 'Aaa']);
    $other = Project::factory()->for($organization)->create(['name' => 'Bbb']);

    TargetProfile::factory()->for($own)->create();
    TargetProfile::factory()->count(3)->for($other)->create();

    // `navCounts()` runs plain unscoped queries that only come out right
    // while `CurrentProject` is actually set (`BelongsToProject`'s global
    // scope) - `HandleInertiaRequests::resolvedProject()` resolving a
    // project for DISPLAY on `account.*` must not leave those queries
    // unscoped, or this would read 4 (both projects) instead of 1.
    $this->actingAs($user)->get(route('account.profile'))
        ->assertInertia(fn ($page) => $page->where('navCounts.targets', 1));
});

it('saves the throttle on continuous discovery', function () {
    reachable();

    $user = member();
    $project = Project::factory()->for($user->organizations()->sole())->create(['url' => 'https://acme.test/']);

    $this->actingAs($user)
        ->putJson(route('settings.project.update'), [
            'name' => $project->name,
            'url' => $project->url,
            'daily_lead_limit' => 25,
            'lead_limit' => 10000,
        ])
        ->assertSessionHasNoErrors();

    expect($project->fresh())
        ->daily_lead_limit->toBe(25)
        ->lead_limit->toBe(10000);
});

it('stores a github token but never sends it back to the browser', function () {
    reachable();

    $user = member();
    $project = Project::factory()->for($user->organizations()->sole())->create(['url' => 'https://acme.test/']);

    $this->actingAs($user)
        ->put(route('settings.project.update'), [
            'name' => $project->name,
            'url' => $project->url,
            'github_token' => 'ghp_secret',
        ])
        ->assertSessionHasNoErrors();

    expect($project->fresh()->github_token)->toBe('ghp_secret');

    $this->actingAs($user)->get(route('settings.project.edit'))
        ->assertInertia(fn ($page) => $page->missing('project.github_token'));
});

it('keeps the stored github token when the field is left blank', function () {
    reachable();

    $user = member();
    $project = Project::factory()->for($user->organizations()->sole())->create([
        'url' => 'https://acme.test/',
        'github_token' => 'ghp_secret',
    ]);

    $this->actingAs($user)
        ->put(route('settings.project.update'), [
            'name' => 'Renamed',
            'url' => $project->url,
            'github_token' => '',
        ])
        ->assertSessionHasNoErrors();

    expect($project->fresh())
        ->name->toBe('Renamed')
        ->github_token->toBe('ghp_secret');
});

it('refuses a lead limit that could never be reached', function () {
    reachable();

    $user = member();
    $project = Project::factory()->for($user->organizations()->sole())->create(['url' => 'https://acme.test/']);

    $this->actingAs($user)
        ->putJson(route('settings.project.update'), [
            'name' => $project->name,
            'url' => $project->url,
            'daily_lead_limit' => 0,
        ])
        ->assertJsonValidationErrors('daily_lead_limit');
});
