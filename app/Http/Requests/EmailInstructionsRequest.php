<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class EmailInstructionsRequest extends FormRequest
{
    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'prompt_instructions' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
