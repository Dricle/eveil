<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * "Posted": the URL of the post, required. It is the proof the step was
 * done.
 */
class LaunchSubmissionRequest extends FormRequest
{
    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'url' => ['required', 'url:http,https', 'max:2048'],
        ];
    }
}
