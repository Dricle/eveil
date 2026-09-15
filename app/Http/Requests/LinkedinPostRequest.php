<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class LinkedinPostRequest extends FormRequest
{
    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'body' => ['required', 'string', 'max:3000'],
        ];
    }
}
