<?php

namespace App\Http\Requests\AppSettings;

use Illuminate\Foundation\Http\FormRequest;

class RedditReplyExampleRequest extends FormRequest
{
    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'body' => ['required', 'string', 'max:10000'],
        ];
    }
}
