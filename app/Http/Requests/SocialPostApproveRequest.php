<?php

namespace App\Http\Requests;

use App\Support\CurrentProject;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Which of the project's granted Bluesky accounts this draft publishes to,
 * checked against the pivot so an ungranted id fails validation rather than
 * publishing somewhere else. Same reasoning as `LinkedinPostApproveRequest`.
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
