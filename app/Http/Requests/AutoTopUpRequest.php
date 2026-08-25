<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Either both fields arrive or neither does: `required_with` each other
 * rather than two independent `nullable`s, so a half-filled form (a
 * threshold with no amount to charge, or the reverse) never saves as
 * "enabled" with a hole in it.
 */
class AutoTopUpRequest extends FormRequest
{
    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'auto_topup_threshold' => ['nullable', 'integer', 'min:0', 'required_with:auto_topup_amount_cents'],
            'auto_topup_amount_cents' => ['nullable', 'integer', 'min:100', 'max:1000000', 'required_with:auto_topup_threshold'],
        ];
    }
}
