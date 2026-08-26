<?php

namespace App\Http\Requests\AppSettings;

use Illuminate\Foundation\Http\FormRequest;

/**
 * The rates are fractions, not percentages, matching how `PromoteProvenEmails`
 * compares them against a variant's own computed rate.
 */
class EmailExampleThresholdRequest extends FormRequest
{
    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'min_sends' => ['required', 'integer', 'min:1', 'max:1000'],
            'min_positive_rate' => ['required', 'numeric', 'min:0.01', 'max:1'],
            'max_unsubscribe_rate' => ['required', 'numeric', 'min:0', 'max:1'],
        ];
    }
}
