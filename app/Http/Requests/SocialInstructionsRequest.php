<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class SocialInstructionsRequest extends FormRequest
{
    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'instructions' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
