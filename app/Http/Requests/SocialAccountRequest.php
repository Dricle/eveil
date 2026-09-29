<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Connecting a Bluesky account: the handle and an app password created in
 * Bluesky's own settings, never the account password. A leading `@` is
 * dropped, since that is how people write handles.
 */
class SocialAccountRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        $this->merge(['handle' => ltrim(trim((string) $this->input('handle')), '@')]);
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'handle' => ['required', 'string', 'max:253'],
            'app_password' => ['required', 'string', 'max:255'],
        ];
    }
}
