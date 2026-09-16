<?php

namespace App\Http\Requests;

use App\Enums\LinkedinPostFrequency;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class LinkedinPostFrequencyRequest extends FormRequest
{
    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'linkedin_post_frequency' => ['required', Rule::enum(LinkedinPostFrequency::class)],
        ];
    }
}
