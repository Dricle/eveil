<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ArticleRequest extends FormRequest
{
    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            'meta_description' => ['nullable', 'string', 'max:500'],
            'body' => ['required', 'string'],
        ];
    }
}
