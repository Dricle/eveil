<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * The floor is Stripe's own practical minimum for a card charge; the ceiling
 * is a fat-finger guard, not a business limit — nothing stops a customer
 * topping up twice.
 */
class TopUpRequest extends FormRequest
{
    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'amount_cents' => ['required', 'integer', 'min:100', 'max:1000000'],
        ];
    }
}
