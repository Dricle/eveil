<?php

namespace Tests;

use App\Models\Project;
use App\Models\User;
use Illuminate\Contracts\Auth\Authenticatable as UserContract;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\URL;

abstract class TestCase extends BaseTestCase
{
    /**
     * Mirrors what `SetCurrentProject` (`app/Http/Middleware/SetCurrentProject.php`)
     * does for a real request: a test acting as a user should not have to
     * name a project on every single `route()` call just to reach one, so
     * this binds the user's own project as the `URL::defaults()` fallback the
     * moment they are signed in. A test exercising two projects, or a
     * stale/forged session pointing elsewhere, still calls the `forProject()`
     * helper (`tests/Pest.php`) afterwards to override this - `URL::defaults()`
     * replaces the whole array on each call, so the later one wins.
     */
    public function actingAs(UserContract $user, $guard = null)
    {
        parent::actingAs($user, $guard);

        if ($user instanceof User) {
            $project = Project::visibleTo($user)->orderBy('name')->first();

            if ($project !== null) {
                URL::defaults(['project:slug' => $project->slug]);
            }
        }

        return $this;
    }
}
