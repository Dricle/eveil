<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Answering a reply by hand. No subject field: the answer belongs to the thread
 * it is in, so it takes the subject of what it answers — a new subject line
 * starts a new conversation as far as every mail client is concerned.
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
        ];
    }
}
