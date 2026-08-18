<?php

namespace App\Http\Requests;

use App\Enums\CampaignStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * A campaign as the user names it. Status is here too because activating one is
 * the same edit — there is nothing else to decide at that moment.
 */
class CampaignRequest extends FormRequest
{
    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'status' => ['required', Rule::enum(CampaignStatus::class)],
        ];
    }
}
