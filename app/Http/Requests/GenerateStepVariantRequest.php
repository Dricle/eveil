<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * What the user wants this new version to test, in their own words. Optional:
 * left blank, the agent picks its own angle.
 */
class GenerateStepVariantRequest extends FormRequest
{
    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'guidance' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
