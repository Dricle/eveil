<?php

namespace App\Http\Requests\AppSettings;

use Illuminate\Foundation\Http\FormRequest;

class LinkedinExampleThresholdRequest extends FormRequest
{
    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'min_likes' => ['required', 'integer', 'min:1', 'max:100000'],
        ];
    }
}
