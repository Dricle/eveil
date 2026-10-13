<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;

/**
 * What to write about: a brief in your own words, or one of the open ideas.
 * One or the other, like asking Evie.
 */
class ArticleStoreRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'brief' => ['required_without:idea_id', 'prohibits:idea_id', 'nullable', 'string', 'max:2000'],
            'idea_id' => ['required_without:brief', 'nullable', 'integer'],
        ];
    }
}
