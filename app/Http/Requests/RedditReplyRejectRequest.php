<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * `reason` is optional: rejecting with nothing typed is still a valid
 * reject. Copy of `LinkedinPostRejectRequest`.
 */
class RedditReplyRejectRequest extends FormRequest
{
    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'reason' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
