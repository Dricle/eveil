<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Dismissing or restoring a checklist row, or saving the user's edit of its
 * draft. Either field alone is a valid request.
 */
class LaunchItemRequest extends FormRequest
{
    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'dismissed' => ['sometimes', 'boolean'],
            'draft' => ['sometimes', 'nullable', 'string', 'max:20000'],
        ];
    }
}
