<?php

namespace App\Http\Requests;

use App\Enums\OrganizationRole;
use App\Support\CurrentProject;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateMemberRoleRequest extends FormRequest
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
            // Owner is never a target here: a second owner / ownership
            // transfer is a separate, more sensitive action.
            'role' => ['required', Rule::enum(OrganizationRole::class)->only([OrganizationRole::Admin, OrganizationRole::Member])],
            'projects' => ['sometimes', 'array'],
            'projects.*' => ['integer'],
        ];
    }
}
