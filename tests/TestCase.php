<?php

namespace Tests;

use App\Models\Project;
use App\Models\User;
use App\Services\Video\SiteCapture;
use Illuminate\Contracts\Auth\Authenticatable as UserContract;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\URL;

abstract class TestCase extends BaseTestCase
{
    /**
     * A test that analyses a site never opens it in a real browser: that
     * would reach the network and take seconds. The tests about capture
     * itself call the real one by name.
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->app->instance(SiteCapture::class, new class extends SiteCapture
        {
            public function capture(string $url, ?string $logoUrl = null): array
            {
                return ['shots' => [], 'logo' => null];
            }
        });
    }

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
