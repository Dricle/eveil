<?php

namespace App\Http\Requests;

use App\Enums\ArticleFrequency;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ArticleFrequencyRequest extends FormRequest
{
    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'article_frequency' => ['required', Rule::enum(ArticleFrequency::class)],
        ];
    }
}
