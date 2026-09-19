<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class RedditReplyDestroyThreadRequest extends FormRequest
{
    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'thread_permalink' => ['required', 'string'],
        ];
    }
}
