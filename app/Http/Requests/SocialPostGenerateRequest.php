<?php

namespace App\Http\Requests;

use App\Enums\SocialPlatform;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SocialPostGenerateRequest extends FormRequest
{
    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'platform' => ['required', Rule::enum(SocialPlatform::class)],
        ];
    }
}
