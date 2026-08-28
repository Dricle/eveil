<?php

namespace App\Http\Requests\AppSettings;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Cloud-only product decisions: what a trial is worth, and the flat rate
 * every top-up converts through - pay-as-you-go, no plan tiers to keep
 * priced separately.
 */
class BillingRequest extends FormRequest
{
    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'trial_credits' => ['required', 'integer', 'min:0'],
            'trial_lead_limit' => ['required', 'integer', 'min:0'],
            'credits_per_dollar' => ['required', 'integer', 'min:1'],
        ];
    }
}
