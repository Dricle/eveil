<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * The monthly budget, in the account's currency units. Zero is legitimate: it
 * is how a person stops Eveil spending there, and the next sync pauses
 * whatever still runs.
 */
class AdAccountRequest extends FormRequest
{
    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'monthly_budget' => ['required', 'numeric', 'min:0', 'max:10000000'],
        ];
    }
}
