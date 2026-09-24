<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ArticleRejectRequest extends FormRequest
{
    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'reason' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
