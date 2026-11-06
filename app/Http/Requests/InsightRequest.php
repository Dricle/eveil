<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * One Insights item marked read or unread, acted on or turned down. Only
 * the fields sent change; `open` is never sent back, a decision sticks.
 */
class InsightRequest extends FormRequest
{
    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'read' => ['sometimes', 'boolean'],
            'status' => ['sometimes', Rule::in(['used', 'dismissed'])],
        ];
    }
}
