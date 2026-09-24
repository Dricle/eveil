<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ArticlePublishRequest extends FormRequest
{
    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'published_url' => ['required', 'string', 'url:http,https', 'max:2048'],
        ];
    }
}
