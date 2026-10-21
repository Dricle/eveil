<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Whether Eveil may spend the project's ads budget on this account. Off is
 * how a person keeps Eveil away from an account the sign-in happened to
 * reach, and the next sync pauses whatever still runs there.
 */
class AdAccountRequest extends FormRequest
{
    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'enabled' => ['required', 'boolean'],
        ];
    }
}
