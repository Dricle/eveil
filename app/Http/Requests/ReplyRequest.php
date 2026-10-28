<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Answering a reply by hand. No subject field: the answer belongs to the thread
 * it is in, so it takes the subject of what it answers. A new subject line
 * starts a new conversation as far as every mail client is concerned.
 *
 * `meeting_at`, when present, is the call this answer books: an invite for it
 * travels with the mail. A time typed without an offset is read in the
 * project's meeting zone, since that is the zone the hours were set in.
 */
class ReplyRequest extends FormRequest
{
    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'body' => ['required', 'string', 'max:10000'],
            'meeting_at' => ['nullable', 'date', 'after:now'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'meeting_at.after' => 'Pick a time that has not passed yet.',
        ];
    }
}
