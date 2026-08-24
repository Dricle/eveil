<?php

namespace App\Http\Requests;

use App\Enums\OrganizationRole;
use App\Support\CurrentProject;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Only Owner or Admin may invite. The organization is the CURRENT project's,
 * never `$user->organizations()->first()`: a user who has accepted a second
 * invitation belongs to more than one, and "first" stops meaning anything
 * once that is possible.
 */
class InviteMemberRequest extends FormRequest
{
    public function authorize(CurrentProject $currentProject): bool
    {
        $role = $currentProject->getOrFail()->organization->roleOf($this->user());

        return in_array($role, [OrganizationRole::Owner, OrganizationRole::Admin], true);
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'email' => ['required', 'email', 'max:255'],
            'role' => ['required', Rule::enum(OrganizationRole::class)->only([OrganizationRole::Admin, OrganizationRole::Member])],
        ];
    }
}
