<?php

namespace App\Policies;

use App\Enums\OrganizationRole;
use App\Models\Project;
use App\Models\User;
use Illuminate\Auth\Access\Response;

/**
 * Owner and Admin see every project in their organization; `member` needs an
 * explicit grant on `project_user` for each one. `Project::visibleTo()`
 * mirrors this exactly, so the sidebar switcher never lists a project this
 * would then 404 on.
 */
class ProjectPolicy
{
    /**
     * Denied as a 404 rather than a 403: a project outside this user's reach
     * must not even confirm that it exists, whether that is because it
     * belongs to another organization or because it was never granted to
     * them.
     */
    public function view(User $user, Project $project): Response
    {
        $role = $project->organization->roleOf($user);

        if ($role === null) {
            return Response::denyAsNotFound();
        }

        if (in_array($role, [OrganizationRole::Owner, OrganizationRole::Admin], true)) {
            return Response::allow();
        }

        return $project->users()->whereKey($user->id)->exists()
            ? Response::allow()
            : Response::denyAsNotFound();
    }

    public function update(User $user, Project $project): Response
    {
        return $this->view($user, $project);
    }

    public function delete(User $user, Project $project): Response
    {
        return $this->view($user, $project);
    }
}
