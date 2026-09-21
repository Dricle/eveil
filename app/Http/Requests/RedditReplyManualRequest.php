<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * "I wrote my own": unlike `RedditReplyApproveRequest`, both fields are
 * required here. There is no drafted body to fall back on, and the whole
 * point of this path is giving Eveil the permalink to track.
 */
class RedditReplyManualRequest extends FormRequest
{
    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'thread_permalink' => ['required', 'string'],
            'body' => ['required', 'string'],
            'comment_permalink' => ['required', 'url', 'max:2048'],
        ];
    }
}
