<?php

namespace App\Http\Requests;

use App\Models\LaunchVenue;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * The tags the user ticked for what the product is. An empty list is valid:
 * only the venues that suit every product stay.
 */
class LaunchTagsRequest extends FormRequest
{
    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'tags' => ['present', 'array'],
            'tags.*' => ['string', Rule::in(array_keys(LaunchVenue::TAGS))],
        ];
    }
}
