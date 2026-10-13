<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;

/**
 * No rule on the profile id beyond its shape: the project scope answers
 * whether it is this project's, and anything else is a 404.
 */
class DiscoveryRunStoreRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'target_profile_id' => ['required', 'integer'],
            'guidance' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
