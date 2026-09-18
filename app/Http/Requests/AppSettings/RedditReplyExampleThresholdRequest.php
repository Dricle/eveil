<?php

namespace App\Http\Requests\AppSettings;

use Illuminate\Foundation\Http\FormRequest;

class RedditReplyExampleThresholdRequest extends FormRequest
{
    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'min_score' => ['required', 'integer', 'min:1', 'max:100000'],
        ];
    }
}
