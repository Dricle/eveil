<?php

namespace App\Actions;

use App\Enums\OrganizationRole;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Validation\ValidationException;

/**
 * Removing yourself is always allowed, the way leaving any organization is:
 * the one hard stop is the last Owner, whoever asks. Losing it would leave
 * the organization unreachable, the same failure `DeleteAccount` already
 * guards against for the account-deletion path.
 */
class RemoveMember
{
    public function handle(Organization $organization, User $target): void
    {
        $role = $organization->roleOf($target);

        if ($role === OrganizationRole::Owner && $organization->users()->wherePivot('role', OrganizationRole::Owner->value)->count() <= 1) {
            throw ValidationException::withMessages(['member' => ['The last owner cannot be removed. Make somebody else owner first.']]);
        }

        $organization->users()->detach($target);

        // Leftover project grants are inert once the org check fails first,
        // but leaving them means a re-invited return visit resurrects access
        // nobody re-granted.
        $target->projects()->whereIn('project_id', $organization->projects()->select('id'))->detach();
    }
}
