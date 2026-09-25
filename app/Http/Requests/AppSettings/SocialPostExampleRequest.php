<?php

namespace App\Http\Requests\AppSettings;

use App\Enums\SocialPlatform;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SocialPostExampleRequest extends FormRequest
{
    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'platform' => ['required', Rule::enum(SocialPlatform::class)],
            'body' => ['required', 'string', 'max:10000'],
        ];
    }
}
