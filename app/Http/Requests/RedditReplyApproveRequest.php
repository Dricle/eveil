<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * The one input "Mark as posted" takes: the resulting comment's permalink,
 * pasted back by the user after posting it on reddit.com themselves.
 * Optional - skipping it is a fully valid "I posted this" too, it just means
 * `FetchRedditReplyStats` never has anything to poll for this row.
 */
class RedditReplyApproveRequest extends FormRequest
{
    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'comment_permalink' => ['nullable', 'url', 'max:2048'],
        ];
    }
}
