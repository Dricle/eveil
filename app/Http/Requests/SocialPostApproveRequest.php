<?php

namespace App\Http\Requests;

use App\Support\CurrentProject;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Which of the project's granted accounts this draft publishes to, chosen by
 * the caller rather than defaulted to the first one: a project can be granted
 * several. Checked against the pivot so an ungranted id fails validation
 * rather than publishing somewhere else.
 */
class SocialPostApproveRequest extends FormRequest
{
    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(CurrentProject $currentProject): array
    {
        return [
            'social_account_id' => [
                'required',
                'integer',
                Rule::exists('project_social_account', 'social_account_id')
                    ->where('project_id', $currentProject->getOrFail()->id),
            ],
        ];
    }
}
