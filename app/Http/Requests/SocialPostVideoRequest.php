<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Asking for a draft's video, or for a new version of it: what to change,
 * and the second of the video the user paused at when they said it.
 */
class SocialPostVideoRequest extends FormRequest
{
    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'feedback' => ['nullable', 'string', 'max:2000'],
            'at' => ['nullable', 'numeric', 'min:0', 'max:600'],
        ];
    }
}
