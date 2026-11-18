<?php

namespace App\Http\Requests;

use App\Models\AdAccount;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Whether Eveil may spend the project's ads budget on this account, or who
 * its ads are posted as. Off is how a person keeps Eveil away from an
 * account the sign-in happened to reach, and the next sync pauses whatever
 * still runs there. The identity must be one the network offered.
 */
class AdAccountRequest extends FormRequest
{
    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        $offered = array_column(AdAccount::query()->findOrFail($this->route('adAccount'))->identities ?? [], 'id');

        return [
            'enabled' => ['required_without:identity_id', 'boolean'],
            'identity_id' => ['sometimes', 'string', Rule::in($offered)],
        ];
    }
}
