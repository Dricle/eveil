<?php

namespace App\Http\Requests;

use App\Enums\OrganizationRole;
use App\Support\CurrentProject;
use Illuminate\Foundation\Http\FormRequest;

/**
 * No fields at all: this only exists to gate who may open the Stripe-hosted
 * `setup`-mode Checkout that saves a card on file. Owner or Admin, same tier
 * as starting a checkout or configuring auto top-up — a saved card is what
 * makes that charge itself possible.
 */
class PaymentMethodRequest extends FormRequest
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
        return [];
    }
}
