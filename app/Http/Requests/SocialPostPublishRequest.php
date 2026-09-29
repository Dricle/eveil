<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * The URL of a post the user published on X by hand. Must be an actual post
 * URL, not a profile: its status id is what identifies the post later.
 */
class SocialPostPublishRequest extends FormRequest
{
    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'url' => ['required', 'string', 'max:2048', 'regex:~^https://(www\.)?(x|twitter)\.com/\w+/status/\d+~'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'url.regex' => 'Paste the link of the post itself, like https://x.com/you/status/123.',
        ];
    }
}
