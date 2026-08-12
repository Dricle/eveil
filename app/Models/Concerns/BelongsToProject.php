<?php

namespace App\Models\Concerns;

use App\Models\Project;
use App\Support\CurrentProject;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Everything a project owns carries `project_id` and is constrained by it
 *. Queries are filtered and new records are stamped automatically,
 * so no call site has to remember.
 *
 * The scope applies only while a current project is set. HTTP requests must
 * always set one — that is where untrusted input reaches queries, and an
 * unscoped query there is the leak the ADR is about. Console commands, jobs and
 * seeders legitimately work across projects; they opt in with
 * `CurrentProject::run()`.
 *
 * @phpstan-require-extends Model
 */
trait BelongsToProject
{
    public static function bootBelongsToProject(): void
    {
        static::addGlobalScope('project', function (Builder $query): void {
            $current = app(CurrentProject::class);

            if ($current->isSet()) {
                $query->where($query->getModel()->qualifyColumn('project_id'), $current->id());
            }
        });

        static::creating(function ($model): void {
            if ($model->project_id === null) {
                $model->project_id = app(CurrentProject::class)->id();
            }
        });
    }

    /**
     * @return BelongsTo<Project, $this>
     */
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }
}
