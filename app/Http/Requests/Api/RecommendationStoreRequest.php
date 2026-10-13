<?php

namespace App\Http\Requests\Api;

use App\Enums\RecommendationKind;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class RecommendationStoreRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'idea' => ['required', 'string', 'max:500'],
            'evidence' => ['required', 'string', 'max:2000'],
            'impact' => ['required', Rule::in(['high', 'medium', 'low'])],
            'effort' => ['required', Rule::in(['high', 'medium', 'low'])],
            'kind' => ['nullable', Rule::enum(RecommendationKind::class)],
        ];
    }
}
