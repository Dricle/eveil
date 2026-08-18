<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * The steps in the order the user dragged them into. The whole list travels,
 * not a single moved id: positions are unique per campaign, so swapping two of
 * them one row at a time collides with the index halfway through.
 */
class CampaignStepOrderRequest extends FormRequest
{
    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'steps' => ['required', 'array', 'min:1'],
            'steps.*' => ['integer'],
        ];
    }
}
