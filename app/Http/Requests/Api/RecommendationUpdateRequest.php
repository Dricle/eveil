<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Only the fields sent change. The status can only be decided (`done`,
 * `archived`), never put back to proposed, same as in the app.
 */
class RecommendationUpdateRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'status' => ['sometimes', Rule::in(['done', 'archived'])],
            'idea' => ['sometimes', 'string', 'max:500'],
            'evidence' => ['sometimes', 'string', 'max:2000'],
            'impact' => ['sometimes', Rule::in(['high', 'medium', 'low'])],
            'effort' => ['sometimes', Rule::in(['high', 'medium', 'low'])],
        ];
    }
}
