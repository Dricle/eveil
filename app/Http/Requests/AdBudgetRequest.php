<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * The project's monthly ads budget, in currency units, and its currency.
 * Zero is legitimate: it is how a person stops Eveil spending, and every
 * running campaign pauses at once.
 */
class AdBudgetRequest extends FormRequest
{
    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'monthly_budget' => ['required', 'numeric', 'min:0', 'max:10000000'],
            'currency' => ['required', 'string', 'size:3', 'alpha', 'uppercase'],
        ];
    }

    protected function prepareForValidation(): void
    {
        if (is_string($this->input('currency'))) {
            $this->merge(['currency' => strtoupper($this->input('currency'))]);
        }
    }
}
