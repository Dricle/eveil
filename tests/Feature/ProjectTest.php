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

it('keeps the project screen behind auth', function () {
    $this->get(route('projects.index'))->assertRedirect(route('login'));

    $this->actingAs(member())->get(route('projects.index'))->assertOk();
});

it('creates a project and starts its analysis', function () {
    reachable();

    $this->actingAs(member())
        ->post(route('projects.store'), ['name' => 'Acme', 'url' => 'acme.test'])
        ->assertRedirect(route('projects.index'))
        ->assertSessionHasNoErrors();

    $project = Project::sole();

    // The scheme is added for the user rather than demanded from them.
    expect($project->url)->toBe('https://acme.test/');

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
        ->put(route('projects.update', $project), ['name' => 'Renamed', 'url' => 'https://acme.test/'])
        ->assertSessionHasNoErrors();

    expect($project->fresh()->name)->toBe('Renamed');
    Queue::assertNothingPushed();

    $this->actingAs($user)
        ->put(route('projects.update', $project), ['name' => 'Renamed', 'url' => 'https://acme.test/en'])
        ->assertSessionHasNoErrors();

    Queue::assertPushed(AnalyzeProject::class);
});

it('deletes a project', function () {
    $user = member();
    $project = Project::factory()->for($user->organizations()->sole())->create();

    $this->actingAs($user)->delete(route('projects.destroy', $project))->assertRedirect(route('projects.index'));

    expect(Project::query()->whereKey($project->getKey())->exists())->toBeFalse();
});

it('hides the projects of another organization', function () {
    $project = Project::factory()->create();
    $stranger = member();

    $this->actingAs($stranger)->get(route('projects.index'))
        ->assertInertia(fn ($page) => $page->where('projects', []));

    $this->actingAs($stranger)->put(route('projects.update', $project), [
        'name' => 'Stolen',
        'url' => 'https://acme.test/',
    ])->assertNotFound();

    $this->actingAs($stranger)->delete(route('projects.destroy', $project))->assertNotFound();

    expect(Project::query()->whereKey($project->getKey())->exists())->toBeTrue();
});
