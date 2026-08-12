<?php

use App\Models\Campaign;
use App\Models\Company;
use App\Models\Lead;
use App\Models\Project;
use App\Support\CurrentProject;

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
