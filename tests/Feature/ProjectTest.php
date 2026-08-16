<?php

use App\Jobs\AnalyzeProject;
use App\Models\Organization;
use App\Models\Project;
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
    $this->get(route('dashboard'))->assertRedirect(route('login'));

    $this->actingAs(member())->get(route('dashboard'))->assertRedirect(route('projects.create'));
});

it('opens straight into a project once one exists', function () {
    $user = member();
    Project::factory()->for($user->organizations()->sole())->create(['name' => 'Acme']);

    $this->actingAs($user)->get(route('dashboard'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->where('currentProject.name', 'Acme'));
});

it('creates a project, starts its analysis and selects it', function () {
    reachable();

    $this->actingAs(member())
        ->post(route('projects.store'), ['name' => 'Acme', 'url' => 'acme.test'])
        ->assertRedirect(route('dashboard'))
        ->assertSessionHasNoErrors();

    $project = Project::sole();

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

it('deletes the current project and falls back to the next one', function () {
    $user = member();
    $organization = $user->organizations()->sole();
    $deleted = Project::factory()->for($organization)->create(['name' => 'Aaa']);
    $kept = Project::factory()->for($organization)->create(['name' => 'Bbb']);

    // 'Aaa' sorts first, so it is what the session lands on unasked.
    $this->actingAs($user)->delete(route('settings.project.destroy'))->assertRedirect(route('dashboard'));

    expect(Project::query()->whereKey($deleted->getKey())->exists())->toBeFalse();

    $this->actingAs($user)->get(route('dashboard'))
        ->assertInertia(fn ($page) => $page->where('currentProject.id', $kept->id));
});

it('never switches to a project outside the user\'s organizations', function () {
    $stranger = member();
    $theirs = Project::factory()->for($stranger->organizations()->sole())->create();
    $someoneElses = Project::factory()->create();

    $this->actingAs($stranger)
        ->put(route('current-project.update', $someoneElses))
        ->assertNotFound();

    // A tampered session falls back to something the user may actually see.
    $this->actingAs($stranger)
        ->withSession(['current_project_id' => $someoneElses->id])
        ->get(route('dashboard'))
        ->assertInertia(fn ($page) => $page->where('currentProject.id', $theirs->id));
});

it('switches the current project', function () {
    $user = member();
    $organization = $user->organizations()->sole();
    Project::factory()->for($organization)->create(['name' => 'Aaa']);
    $other = Project::factory()->for($organization)->create(['name' => 'Bbb']);

    $this->actingAs($user)
        ->from(route('dashboard'))
        ->put(route('current-project.update', $other))
        ->assertRedirect(route('dashboard'));

    expect(session('current_project_id'))->toBe($other->id);
});

it('lets a user with no project still reach their account', function () {
    $this->actingAs(member())->get(route('account.profile'))->assertOk();
});
