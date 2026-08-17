<?php

namespace App\Http\Requests\AppSettings;

use App\Enums\HostKind;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Correcting a verdict locks it: the point of the screen is that a human
 * outranks the model, and a correction that a re-triage could overwrite next
 * week is not a correction. Unlocking is explicit, from the same form.
 */
class KnownHostRequest extends FormRequest
{
    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'kind' => ['required', Rule::enum(HostKind::class)],
            'reason' => ['nullable', 'string', 'max:500'],
            'is_locked' => ['boolean'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['is_locked' => $this->boolean('is_locked')]);
    }
}
