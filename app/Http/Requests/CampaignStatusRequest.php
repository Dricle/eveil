<?php

namespace App\Http\Requests;

use App\Enums\CampaignStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Started or paused, and nothing else. Kept apart from the campaign's own form
 * because the switch is thrown from the list too, where the name is not being
 * edited and posting a stale copy of it would overwrite a rename.
 */
class CampaignStatusRequest extends FormRequest
{
    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'status' => ['required', Rule::enum(CampaignStatus::class)],
        ];
    }
}
