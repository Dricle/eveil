<?php

use App\Jobs\AnalyzeRepo;
use App\Models\CodeRepository;
use App\Models\Organization;
use App\Models\Project;
use App\Models\User;
use Illuminate\Support\Facades\Queue;

/**
 * @return array{0: User, 1: Project}
 */
function repoOwner(): array
{
    $user = User::factory()->create();
    $organization = Organization::factory()->create();
    $organization->users()->attach($user, ['role' => 'owner']);
    $project = Project::factory()->for($organization)->create();

    return [$user, $project];
}

it('links a github repo and starts reading it', function () {
    Queue::fake();
    [$user] = repoOwner();

    $this->actingAs($user)
        ->post(route('settings.repositories.store'), ['url' => 'https://github.com/acme/widgets'])
        ->assertSessionHasNoErrors();

    $repository = CodeRepository::query()->sole();

    expect($repository->url)->toBe('https://github.com/acme/widgets')
        ->and($repository->name)->toBe('acme/widgets');

    Queue::assertPushed(AnalyzeRepo::class, fn (AnalyzeRepo $job): bool => $job->codeRepository->is($repository));
});

it('refuses anything that is not a github.com URL', function () {
    [$user] = repoOwner();

    $this->actingAs($user)
        ->post(route('settings.repositories.store'), ['url' => 'https://gitlab.com/acme/widgets'])
        ->assertSessionHasErrors('url');

    expect(CodeRepository::query()->count())->toBe(0);
});

it('refuses linking the same repo twice to one project', function () {
    [$user, $project] = repoOwner();
    CodeRepository::factory()->for($project)->create(['url' => 'https://github.com/acme/widgets']);

    $this->actingAs($user)
        ->post(route('settings.repositories.store'), ['url' => 'https://github.com/acme/widgets'])
        ->assertSessionHasErrors('url');
});

it('unlinks a repo and drops its findings from the knowledge base', function () {
    [$user, $project] = repoOwner();
    $repository = CodeRepository::factory()->for($project)->create();
    $project->update(['knowledge_base' => [
        'what_it_does' => 'Sells widgets.',
        'repositories' => [['code_repository_id' => $repository->id, 'name' => $repository->name]],
    ]]);

    $this->actingAs($user)
        ->delete(route('settings.repositories.destroy', $repository))
        ->assertSessionHasNoErrors();

    expect(CodeRepository::query()->withoutGlobalScopes()->count())->toBe(0)
        ->and($project->fresh()->knowledge_base['repositories'])->toBe([])
        // Nothing else about the portrait should move.
        ->and($project->fresh()->knowledge_base['what_it_does'])->toBe('Sells widgets.');
});

it('does not let a project unlink another project\'s repo', function () {
    [$user] = repoOwner();
    $other = CodeRepository::factory()->create();

    $this->actingAs($user)
        ->delete(route('settings.repositories.destroy', $other))
        ->assertNotFound();

    expect(CodeRepository::query()->withoutGlobalScopes()->count())->toBe(1);
});
