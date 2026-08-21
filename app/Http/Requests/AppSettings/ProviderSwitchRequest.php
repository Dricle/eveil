<?php

namespace App\Http\Requests\AppSettings;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Which provider every agent should move to.
 *
 * Not restricted to the `Lab` cases: an OpenAI-compatible endpoint is
 * referenced by its config key, which no enum covers. Whether it can actually
 * be called is a different question, and the controller asks it.
 */
class ProviderSwitchRequest extends FormRequest
{
    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'provider' => ['required', 'string', 'max:64'],
        ];
    }
}
