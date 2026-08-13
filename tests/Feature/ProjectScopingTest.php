<?php

use App\Models\Campaign;
use App\Models\Company;
use App\Models\EmailAccount;
use App\Models\Lead;
use App\Models\Organization;
use App\Models\Project;
use App\Support\CurrentProject;
use Illuminate\Database\QueryException;

/**
 * A leak between projects is the worst bug this app can ship. These
 * tests are the guard.
 */
it('hides records belonging to another project', function () {
    $mine = Project::factory()->create();
    $theirs = Project::factory()->create();

    Lead::factory()->for($mine)->create();
    Lead::factory()->for($theirs)->create();

    app(CurrentProject::class)->run($mine, function () {
        expect(Lead::count())->toBe(1);
    });
});

it('cannot reach another project record by id', function () {
    $mine = Project::factory()->create();
    $theirs = Project::factory()->create();
    $foreign = Lead::factory()->for($theirs)->create();

    app(CurrentProject::class)->run($mine, function () use ($foreign) {
        expect(Lead::find($foreign->id))->toBeNull();
    });
});

it('scopes every project-owned model', function (string $model) {
    $mine = Project::factory()->create();
    $theirs = Project::factory()->create();

    $model::factory()->for($theirs)->create();

    app(CurrentProject::class)->run($mine, function () use ($model) {
        expect($model::count())->toBe(0);
    });
})->with([Lead::class, Company::class, Campaign::class]);

it('stamps the current project on new records', function () {
    $project = Project::factory()->create();

    $lead = app(CurrentProject::class)->run($project, fn () => Lead::factory()->create(['project_id' => null]));

    expect($lead->project_id)->toBe($project->id);
});

it('restores the previous project even when the callback throws', function () {
    $first = Project::factory()->create();
    $second = Project::factory()->create();
    $current = app(CurrentProject::class);

    $current->run($first, function () use ($current, $first, $second) {
        try {
            $current->run($second, fn () => throw new RuntimeException('boom'));
        } catch (RuntimeException) {
            // Swallowed on purpose: the point is what the context looks like after.
        }

        expect($current->id())->toBe($first->id);
    });

    expect($current->isSet())->toBeFalse();
});

it('leaves queries unscoped when no project is set, for console and jobs', function () {
    Lead::factory()->count(2)->create();

    expect(Lead::count())->toBe(2);
});

it('grants a mailbox to a project only when it is attached', function () {
    $organization = Organization::factory()->create();
    $restogo = Project::factory()->create(['organization_id' => $organization->id]);
    $immodb = Project::factory()->create(['organization_id' => $organization->id]);

    $personal = EmailAccount::factory()->create([
        'organization_id' => $organization->id,
        'from_email' => 'clement@dricle.be',
    ]);
    $productAddress = EmailAccount::factory()->create([
        'organization_id' => $organization->id,
        'from_email' => 'contact@restogo.be',
    ]);

    $personal->projects()->attach([$restogo->id, $immodb->id]);
    $productAddress->projects()->attach($restogo->id);

    expect($restogo->emailAccounts()->pluck('from_email')->sort()->values()->all())
        ->toBe(['clement@dricle.be', 'contact@restogo.be'])
        ->and($immodb->emailAccounts()->pluck('from_email')->all())
        ->toBe(['clement@dricle.be']);
});

it('leaves a new project unable to send until a mailbox is attached', function () {
    // The reason this is a pivot and not a nullable `project_id`: "null means
    // every project" would silently hand the founder's personal address to the
    // next client project created.
    $organization = Organization::factory()->create();
    $existing = Project::factory()->create(['organization_id' => $organization->id]);

    EmailAccount::factory()
        ->create(['organization_id' => $organization->id, 'from_email' => 'clement@dricle.be'])
        ->projects()->attach($existing->id);

    $freshProject = Project::factory()->create(['organization_id' => $organization->id]);

    expect($freshProject->emailAccounts()->count())->toBe(0)
        ->and($organization->emailAccounts()->count())->toBe(1);
});

it('refuses to grant the same mailbox to one project twice', function () {
    $project = Project::factory()->create();
    $account = EmailAccount::factory()->create(['organization_id' => $project->organization_id]);

    $account->projects()->attach($project->id);

    expect(fn () => $account->projects()->attach($project->id))
        ->toThrow(QueryException::class);
});
