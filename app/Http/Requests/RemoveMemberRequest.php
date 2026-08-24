<?php

namespace App\Http\Requests;

use App\Enums\OrganizationRole;
use App\Support\CurrentProject;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Anyone may remove themselves, the way leaving an organization always works.
 * Removing somebody else needs Owner or Admin. The last-owner guard itself
 * lives in `RemoveMember`, not here: that is a business rule about the
 * organization's shape, not about who is allowed to ask.
 */
class RemoveMemberRequest extends FormRequest
{
    public function authorize(CurrentProject $currentProject): bool
    {
        if ((int) $this->route('user') === $this->user()->id) {
            return true;
        }

        $role = $currentProject->getOrFail()->organization->roleOf($this->user());

        return in_array($role, [OrganizationRole::Owner, OrganizationRole::Admin], true);
    }
}
