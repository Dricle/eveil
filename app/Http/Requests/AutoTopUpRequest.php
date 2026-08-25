<?php

namespace App\Http\Requests;

use App\Enums\OrganizationRole;
use App\Support\CurrentProject;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Either both fields arrive or neither does: `required_with` each other
 * rather than two independent `nullable`s, so a half-filled form (a
 * threshold with no amount to charge, or the reverse) never saves as
 * "enabled" with a hole in it.
 *
 * Owner or Admin only: this is what makes a card on file start charging
 * itself, unattended, same sensitivity tier as a checkout.
 */
class AutoTopUpRequest extends FormRequest
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
            'auto_topup_threshold' => ['nullable', 'integer', 'min:0', 'required_with:auto_topup_amount_cents'],
            'auto_topup_amount_cents' => ['nullable', 'integer', 'min:100', 'max:1000000', 'required_with:auto_topup_threshold'],
        ];
    }
}
