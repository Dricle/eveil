<?php

namespace App\Actions;

use App\Enums\OrganizationRole;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Validation\ValidationException;

/**
 * Same last-owner rule as `RemoveMember`: demoting the sole Owner is exactly
 * as dangerous as removing them, and the organization ends up equally
 * unreachable either way.
 */
class UpdateMemberRole
{
    public function handle(Organization $organization, User $target, OrganizationRole $role): void
    {
        $current = $organization->roleOf($target);

        if ($current === OrganizationRole::Owner && $role !== OrganizationRole::Owner
            && $organization->users()->wherePivot('role', OrganizationRole::Owner->value)->count() <= 1) {
            throw ValidationException::withMessages(['role' => ['The last owner cannot be demoted. Make somebody else owner first.']]);
        }

        $organization->users()->updateExistingPivot($target->id, ['role' => $role->value]);
    }
}
