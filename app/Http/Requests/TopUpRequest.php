<?php

namespace App\Http\Requests;

use App\Enums\OrganizationRole;
use App\Support\CurrentProject;
use Illuminate\Foundation\Http\FormRequest;

/**
 * The floor is Stripe's own practical minimum for a card charge; the ceiling
 * is a fat-finger guard, not a business limit — nothing stops a customer
 * topping up twice.
 *
 * Owner or Admin only: any member starting a checkout would be any member
 * spending the organization's money, with nobody else in the loop.
 */
class TopUpRequest extends FormRequest
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
            'amount_cents' => ['required', 'integer', 'min:100', 'max:1000000'],
        ];
    }
}
