<?php

namespace App\Actions;

use App\Models\Organization;
use App\Models\User;

/**
 * Grants a `member` exactly the projects given, scoped to ONE organization.
 *
 * `$user->projects()` carries no organization of its own, so a plain `sync()`
 * would wipe out grants the same person holds in a different organization
 * they also belong to. This diffs against only THIS organization's projects
 * and touches nothing outside it.
 *
 * Owner and Admin never need this: they bypass the grant entirely
 * (`ProjectPolicy`), so calling it for them would be silent no-op busywork.
 * The caller decides whether to call it at all.
 */
class SetMemberProjectAccess
{
    /**
     * @param  array<int, int>  $projectIds
     */
    public function handle(Organization $organization, User $target, array $projectIds): void
    {
        $organizationProjectIds = $organization->projects()->pluck('id')->all();
        $desired = array_intersect($projectIds, $organizationProjectIds);

        $current = $target->projects()
            ->whereIn('projects.id', $organizationProjectIds)
            ->pluck('projects.id')
            ->all();

        $target->projects()->detach(array_diff($current, $desired));
        $target->projects()->attach(array_diff($desired, $current));
    }
}
