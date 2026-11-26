<?php

namespace App\Http\Requests;

use App\Enums\LaunchVenueKind;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * A venue the catalog does not list, added to the project's checklist.
 */
class LaunchVenueRequest extends FormRequest
{
    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'url' => ['required', 'url:http,https', 'max:2048'],
            'kind' => ['required', Rule::enum(LaunchVenueKind::class)],
            'is_paid' => ['boolean'],
            'price_note' => ['nullable', 'string', 'max:255'],
            'cooldown_days' => ['nullable', 'integer', 'min:1', 'max:3650'],
            'rules' => ['nullable', 'string', 'max:5000'],
        ];
    }
}
