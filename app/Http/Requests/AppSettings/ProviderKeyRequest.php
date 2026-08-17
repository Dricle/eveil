<?php

namespace App\Http\Requests\AppSettings;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Laravel\Ai\Enums\Lab;

/**
 * The provider name is constrained to what `laravel/ai` can build a driver for
 * — a typo would otherwise store a key nothing will ever read.
 */
class ProviderKeyRequest extends FormRequest
{
    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'provider' => ['required', 'string', Rule::in(collect(Lab::cases())->map(fn (Lab $lab): string => $lab->value))],
            'key' => ['required', 'string', 'max:500'],
        ];
    }
}
