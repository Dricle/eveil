<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class TargetProfileSubredditRequest extends FormRequest
{
    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:100'],
        ];
    }
}
