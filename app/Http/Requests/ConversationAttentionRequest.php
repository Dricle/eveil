<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Marking a conversation done, or putting it back. Both directions of the
 * same toggle, so one field rather than two routes.
 */
class ConversationAttentionRequest extends FormRequest
{
    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'resolved' => ['required', 'boolean'],
        ];
    }
}
