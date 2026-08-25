<?php

namespace App\Http\Requests\AppSettings;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Never edited in place: saving this adds a new `credit_prices` row rather
 * than updating one, so a transaction already charged at the old rate stays
 * reproducible.
 */
class CreditPriceRequest extends FormRequest
{
    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'credits' => ['required', 'integer', 'min:1'],
        ];
    }
}
