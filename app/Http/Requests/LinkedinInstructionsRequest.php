<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class LinkedinInstructionsRequest extends FormRequest
{
    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'linkedin_prompt_instructions' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
