<?php

namespace App\Policies;

use App\Models\Project;
use App\Models\User;
use Illuminate\Auth\Access\Response;

/**
 * Access follows the organization until per-project grants get a screen.
 */
class ProjectPolicy
{
    /**
     * Denied as a 404 rather than a 403: a project in somebody else's
     * organization must not even confirm that it exists.
     */
    public function update(User $user, Project $project): Response
    {
        return $user->organizations()->whereKey($project->organization_id)->exists()
            ? Response::allow()
            : Response::denyAsNotFound();
    }

    public function delete(User $user, Project $project): Response
    {
        return $this->update($user, $project);
    }
}
