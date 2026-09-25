<?php

namespace App\Http\Requests;

use App\Enums\SocialPostFrequency;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SocialPostFrequencyRequest extends FormRequest
{
    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'x_post_frequency' => ['required', Rule::enum(SocialPostFrequency::class)],
            'bluesky_post_frequency' => ['required', Rule::enum(SocialPostFrequency::class)],
        ];
    }
}
