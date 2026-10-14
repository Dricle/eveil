<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;

/**
 * The app's paste box as a list: one email or website per entry, decided in
 * `MarkKnownClients`, same cap.
 */
class KnownClientStoreRequest extends FormRequest
{
    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'entries' => ['required', 'array', 'list', 'min:1', 'max:500'],
            'entries.*' => ['string', 'max:255'],
        ];
    }
}
