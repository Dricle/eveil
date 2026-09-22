<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * The two terminal states a person sets by hand. `proposed` is never sent
 * back through this route: only a fresh analysis or Evie, correcting a
 * mistake mid-conversation, ever writes that one.
 */
class RecommendationStatusRequest extends FormRequest
{
    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'status' => ['required', Rule::in(['done', 'archived'])],
        ];
    }
}
